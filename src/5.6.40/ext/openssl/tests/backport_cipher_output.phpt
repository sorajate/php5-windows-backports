--TEST--
Cipher output capacity preserves ordinary encryption and wrapping where available
--EXTENSIONS--
openssl
--FILE--
<?php
$key = str_repeat('K', 16);
$iv = str_repeat('I', 16);
foreach (array(0, 1, 15, 16, 17, 32, 255) as $length) {
    $data = str_repeat('x', $length);
    $encrypted = openssl_encrypt($data, 'aes-128-cbc', $key, OPENSSL_RAW_DATA, $iv);
    var_dump(is_string($encrypted) && openssl_decrypt($encrypted, 'aes-128-cbc', $key, OPENSSL_RAW_DATA, $iv) === $data);
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
