--TEST--
CVE-2026-7260: an open TAR link stream fails read and seek safely after its target is removed
--SKIPIF--
<?php if (!extension_loaded('phar')) die('skip phar required'); ?>
--INI--
phar.require_hash=0
phar.readonly=0
--FILE--
<?php
require dirname(__FILE__) . '/files/security_tar_helpers.inc';
$file = dirname(__FILE__) . '/' . basename(__FILE__, '.php') . '.tar';
file_put_contents($file, phar_security_tar_entry('target', 'safe')
    . phar_security_tar_entry('alias', '', '2', 'target') . str_repeat("\0", 1024));
$p = new PharData($file);
$fp = fopen('phar://' . $file . '/alias', 'r');
unset($p['target']);
var_dump(@fseek($fp, 0, SEEK_END));
$result = @fread($fp, 1);
var_dump($result === '' || $result === false);
fclose($fp);
?>
--CLEAN--
<?php unlink(dirname(__FILE__) . '/' . basename(__FILE__, '.clean.php') . '.tar'); ?>
--EXPECT--
int(-1)
bool(true)
