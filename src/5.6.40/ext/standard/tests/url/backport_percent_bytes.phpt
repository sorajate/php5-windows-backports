--TEST--
URL decoding passes high percent-escape bytes to ctype in the unsigned-char range
--FILE--
<?php
$safe = true;
$checked = 0;
foreach (array('urldecode', 'rawurldecode') as $decode) {
    for ($byte = 128; $byte < 256; $byte++) {
        foreach (array('%' . chr($byte), '%' . chr($byte) . '0', '%0' . chr($byte), '%' . chr($byte) . chr($byte)) as $input) {
            $safe = $safe && $decode($input) === $input;
            $checked++;
        }
    }
}
var_dump($safe, $checked);

$controls = array(
    '%41' => 'A', '%4a' => 'J', '%4A' => 'J', '%00' => "\0", '%ff' => "\xff",
    '%2B' => '+', '%25' => '%', '%' => '%', '%0' => '%0', '%GG' => '%GG',
    'a%20b' => 'a b'
);
$safe = true;
foreach ($controls as $input => $expected) {
    $safe = $safe && urldecode($input) === $expected && rawurldecode($input) === $expected;
}
var_dump($safe);
var_dump(urldecode('a+b') === 'a b', rawurldecode('a+b') === 'a+b');
?>
--EXPECT--
bool(true)
int(1024)
bool(true)
bool(true)
bool(true)
