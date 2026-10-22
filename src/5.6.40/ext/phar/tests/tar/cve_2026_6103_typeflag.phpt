--TEST--
CVE-2026-6103: TAR data-bearing type flags must not expose payload headers as entries
--SKIPIF--
<?php if (!extension_loaded('phar')) die('skip phar required'); ?>
--INI--
phar.require_hash=0
--FILE--
<?php
require dirname(__FILE__) . '/files/security_tar_helpers.inc';
$prefix = dirname(__FILE__) . '/' . basename(__FILE__, '.php');
foreach (array('7', '8', '9', 'V', 'S', 'g', 'x') as $i => $type) {
    $file = $prefix . '.' . $i . '.tar';
    $payload = phar_security_tar_header('injected', '0');
    file_put_contents($file, phar_security_tar_entry('carrier', $payload, $type)
        . phar_security_tar_entry('after', 'normal') . str_repeat("\0", 1024));
    $p = new PharData($file);
    var_dump(!isset($p['injected']) && $p['after']->getContent() === 'normal');
}
/* Unsupported GNU long link targets must not be registered as ordinary files. */
$file = $prefix . '.longlink.tar';
file_put_contents($file, phar_security_tar_entry('././@LongLink', 'target', 'K') . str_repeat("\0", 1024));
try {
    $p = new PharData($file);
    echo "accepted long link\n";
} catch (Exception $e) {
    echo strpos($e->getMessage(), 'unsupported GNU long link') !== false ? "unsupported long link\n" : $e->getMessage() . "\n";
}
/* The supported GNU long filename path must use the same checked size parse. */
$file = $prefix . '.longname.tar';
file_put_contents($file, phar_security_tar_header('././@LongLink', "40000000001\0", 'L')
    . phar_security_tar_entry('injected', '') . str_repeat("\0", 1024));
try {
    $p = new PharData($file);
    echo "accepted wrapped long filename\n";
} catch (Exception $e) {
    echo strpos($e->getMessage(), 'invalid entry size') !== false ? "invalid long filename size\n" : $e->getMessage() . "\n";
}
?>
--CLEAN--
<?php
$prefix = dirname(__FILE__) . '/' . basename(__FILE__, '.clean.php');
foreach (glob($prefix . '.*.tar') as $file) unlink($file);
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
unsupported long link
invalid long filename size
