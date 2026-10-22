--TEST--
CVE-2026-6103: TAR numeric fields must not wrap or silently accept invalid encodings
--SKIPIF--
<?php if (!extension_loaded('phar')) die('skip phar required'); ?>
--INI--
phar.require_hash=0
--FILE--
<?php
require dirname(__FILE__) . '/files/security_tar_helpers.inc';
$prefix = dirname(__FILE__) . '/' . basename(__FILE__, '.php');
$cases = array(
    '40000000000', '40000000001', '40000001000',
    '37777777777', '37777777001', '777777777777',
    '00000000001x', "00000000008\0", "1\0" . '0000000000',
    chr(128) . str_repeat("\0", 11)
);
foreach ($cases as $i => $size) {
    $file = $prefix . '.bad' . $i . '.tar';
    file_put_contents($file, phar_security_tar_header('outer', $size)
        . phar_security_tar_entry('injected', 'not an entry') . str_repeat("\0", 1024));
    try {
        $p = new PharData($file);
        echo "accepted invalid size\n";
    } catch (Exception $e) {
        echo strpos($e->getMessage(), 'invalid entry size') !== false ? "invalid size\n" : $e->getMessage() . "\n";
    }
}
foreach (array(
    array('mode' => '0000644x'),
    array('mtime' => "40000000000\0"),
    array('checksum' => '000001x ')
) as $i => $fields) {
    $file = $prefix . '.field' . $i . '.tar';
    file_put_contents($file, phar_security_tar_entry('control', '', '0', '', $fields) . str_repeat("\0", 1024));
    try {
        $p = new PharData($file);
        echo "accepted invalid numeric field\n";
    } catch (Exception $e) {
        echo strpos($e->getMessage(), 'invalid') !== false ? "invalid numeric field\n" : $e->getMessage() . "\n";
    }
}
/* This size is valid numerically; the tiny fixture is missing its data. */
$file = $prefix . '.limit.tar';
file_put_contents($file, phar_security_tar_header('large', "37777777000\0") . str_repeat("\0", 1024));
try {
    $p = new PharData($file);
    echo "accepted truncated archive\n";
} catch (Exception $e) {
    echo strpos($e->getMessage(), 'truncated') !== false ? "valid size, truncated data\n" : $e->getMessage() . "\n";
}
?>
--CLEAN--
<?php
$prefix = dirname(__FILE__) . '/' . basename(__FILE__, '.clean.php');
foreach (glob($prefix . '.*.tar') as $file) unlink($file);
?>
--EXPECT--
invalid size
invalid size
invalid size
invalid size
invalid size
invalid size
invalid size
invalid size
invalid size
invalid size
invalid numeric field
invalid numeric field
invalid numeric field
valid size, truncated data
