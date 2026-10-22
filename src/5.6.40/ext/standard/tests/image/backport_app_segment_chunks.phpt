--TEST--
getimagesize preserves complete APPn bytes through positive short reads and stream filters
--FILE--
<?php
class BackportAppChunkStream {
    public $context;
    public static $data = '';
    public static $chunk = 13;
    private $position = 0;
    public function stream_open($path, $mode, $options, &$opened_path) { return true; }
    public function stream_read($count) {
        $data = substr(self::$data, $this->position, min($count, self::$chunk));
        $this->position += strlen($data);
        return $data;
    }
    public function stream_eof() { return $this->position >= strlen(self::$data); }
    public function stream_tell() { return $this->position; }
    public function stream_seek($offset, $whence) {
        /* Deliberately non-seekable: PHP 5.6 stream filters keep decoder
         * state across fseek(), and getimagesize() seeks after SOF blocks. */
        return false;
    }
    public function stream_stat() { return array(); }
}
stream_wrapper_register('backportapp', 'BackportAppChunkStream');

function backport_app_jpeg($payload, $duplicate = false) {
    $jpeg = "\xff\xd8\xff\xe1" . pack('n', strlen($payload) + 2) . $payload;
    if ($duplicate) $jpeg .= "\xff\xe1" . pack('n', 9) . 'ignored';
    return $jpeg . "\xff\xc0" . pack('n', 17) . "\x08" . pack('n2', 2, 3)
        . "\x03" . str_repeat("\0", 9) . "\xff\xda";
}

$payload = str_repeat("abc123\0", 2000);
$jpeg = backport_app_jpeg($payload, true);
$info = array();
$result = getimagesizefromstring($jpeg, $info);
var_dump($result[0] === 3 && $result[1] === 2 && $info['APP1'] === $payload);

foreach (array(13, 8192) as $chunk) {
    BackportAppChunkStream::$data = $jpeg;
    BackportAppChunkStream::$chunk = $chunk;
    $info = array();
    $result = getimagesize('backportapp://image', $info);
    var_dump($result[0] === 3 && $result[1] === 2 && $info['APP1'] === $payload);
}

$info = array();
BackportAppChunkStream::$data = base64_encode($jpeg);
BackportAppChunkStream::$chunk = 13;
$result = getimagesize('php://filter/read=convert.base64-decode/resource=backportapp://image', $info);
var_dump($result[0] === 3 && $result[1] === 2 && $info['APP1'] === $payload);

/* Maximum APP segment payload is 65535 minus its two-byte length. */
$payload = str_repeat('z', 65533);
BackportAppChunkStream::$data = backport_app_jpeg($payload);
BackportAppChunkStream::$chunk = 37;
$info = array();
$result = getimagesize('backportapp://image', $info);
var_dump($result[0] === 3 && $result[1] === 2 && $info['APP1'] === $payload);
stream_wrapper_unregister('backportapp');
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
