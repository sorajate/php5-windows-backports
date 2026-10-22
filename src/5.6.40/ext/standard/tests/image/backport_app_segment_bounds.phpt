--TEST--
getimagesize does not publish an APPn segment after a positive short read at EOF
--FILE--
<?php
$safe = true;
for ($marker = 0; $marker < 16; $marker++) {
    $info = array();
    $jpeg = "\xff\xd8\xff" . chr(0xe0 + $marker) . pack('n', 34) . 'x';
    $result = getimagesizefromstring($jpeg, $info);
    $safe = $safe && $result === false && !array_key_exists('APP' . $marker, $info);
}
var_dump($safe);

$filename = __DIR__ . '/backport_app_segment_bounds.tmp';
file_put_contents($filename, "\xff\xd8\xff\xe1" . pack('n', 34) . 'x');
$info = array();
var_dump(getimagesize($filename, $info) === false && !isset($info['APP1']));
unlink($filename);

/* Keep a complete earlier segment while rejecting the incomplete next one. */
$jpeg = "\xff\xd8\xff\xe0" . pack('n', 4) . 'ok'
    . "\xff\xe1" . pack('n', 34) . 'x';
$info = array();
var_dump(getimagesizefromstring($jpeg, $info) === false
    && isset($info['APP0']) && $info['APP0'] === 'ok' && !isset($info['APP1']));
?>
--CLEAN--
<?php
@unlink(__DIR__ . '/backport_app_segment_bounds.tmp');
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
