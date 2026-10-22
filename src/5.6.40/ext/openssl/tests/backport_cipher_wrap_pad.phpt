--TEST--
WRAP-PAD capacity handles small and unaligned inputs when supported
--EXTENSIONS--
openssl
--SKIPIF--
<?php
if (!in_array('aes-256-wrap-pad', openssl_get_cipher_methods())) die('skip WRAP-PAD cipher unavailable in this OpenSSL build');
?>
--FILE--
<?php
$key = str_repeat('K', 32);
foreach (array(1, 7, 8, 9, 15, 16, 17) as $length) {
    $data = str_repeat('x', $length);
    $encrypted = @openssl_encrypt($data, 'aes-256-wrap-pad', $key, OPENSSL_RAW_DATA);
    var_dump(is_string($encrypted) && strlen($encrypted) <= $length + 16 &&
        @openssl_decrypt($encrypted, 'aes-256-wrap-pad', $key, OPENSSL_RAW_DATA) === $data);
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
