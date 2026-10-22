--TEST--
Convert filters preserve binary line-break strings
--FILE--
<?php
function convert_with_break($filter, $data, $length) {
    $stream = fopen('php://temp', 'w+');
    stream_filter_append($stream, $filter, STREAM_FILTER_WRITE,
        array('line-length' => $length, 'line-break-chars' => "\r\0\n"));
    fwrite($stream, $data);
    rewind($stream);
    $result = stream_get_contents($stream);
    fclose($stream);
    return $result;
}
echo bin2hex(convert_with_break('convert.base64-encode', 'abcdef', 4)), "\n";
echo bin2hex(convert_with_break('convert.quoted-printable-encode', 'abcdefgh', 5)), "\n";
echo bin2hex(convert_with_break('convert.quoted-printable-decode', "A=\r\0\nB", 5)), "\n";
echo base64_decode(convert_with_break('convert.base64-encode', 'abc', 4)), "\n";
?>
--EXPECT--
59574a6a0d000a5a47566d
616263643d0d000a65666768
4142
abc
