--TEST--
CVE-2026-6103: TAR valid octal fields, padding boundaries and maximum timestamp remain accepted
--SKIPIF--
<?php if (!extension_loaded('phar')) die('skip phar required'); ?>
--INI--
phar.require_hash=0
--FILE--
<?php
require dirname(__FILE__) . '/files/security_tar_helpers.inc';
$file = dirname(__FILE__) . '/' . basename(__FILE__, '.php') . '.tar';
$tar = '';
foreach (array(0, 1, 511, 512, 513) as $size) {
    $tar .= phar_security_tar_entry('file' . $size, str_repeat('a', $size));
}
/* UINT32_MAX is a legitimate timestamp, not an error sentinel. */
$tar .= phar_security_tar_entry('timestamp', 'maximum', '0', '', array('mtime' => "37777777777\0"));
$tar .= phar_security_tar_entry('mode', 'masked', '0', '', array('mode' => '77777777'));
$tar .= phar_security_tar_header('spaces', "         1 \0") . 'z' . str_repeat("\0", 511);
$tar .= phar_security_tar_header('zero', str_repeat('0', 12));
file_put_contents($file, $tar . str_repeat("\0", 1024));
$p = new PharData($file);
foreach (array(0, 1, 511, 512, 513) as $size) {
    var_dump($p['file' . $size]->getContent() === str_repeat('a', $size));
}
var_dump($p['timestamp']->getContent());
var_dump($p['mode']->getContent());
var_dump($p['spaces']->getContent());
var_dump($p['zero']->getContent());
?>
--CLEAN--
<?php unlink(dirname(__FILE__) . '/' . basename(__FILE__, '.clean.php') . '.tar'); ?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
string(7) "maximum"
string(6) "masked"
string(1) "z"
string(0) ""
