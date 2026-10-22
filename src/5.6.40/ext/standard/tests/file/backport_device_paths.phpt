--TEST--
CVE-2026-17545: qualified NUL devices are refused while bare NUL and ordinary names remain usable
--SKIPIF--
<?php if (substr(PHP_OS, 0, 3) !== 'WIN') die('skip Windows only'); ?>
--FILE--
<?php
/* Only the nonblocking NUL device is used for integration coverage. Other
 * reserved devices are covered by the native classifier/fake-sink test. */
$dir = dirname(__FILE__) . '/' . basename(__FILE__, '.php') . '.dir';
mkdir($dir);
$normal = $dir . '/normal.txt';
file_put_contents($normal, 'safe');
foreach (array('NUL', 'nul', './NUL') as $name) {
    $path = $dir . '/' . $name;
    $fp = @fopen($path, 'wb');
    var_dump($fp === false);
    if (is_resource($fp)) fclose($fp);
    var_dump(@file_put_contents($path, 'x') === false);
    var_dump(@copy($normal, $path) === false);
    var_dump(@touch($path) === false);
}
var_dump(@fopen('file://' . str_replace('\\', '/', realpath($dir)) . '/NUL', 'wb') === false);
var_dump(@rename($normal, $dir . '/NUL') === false);
var_dump(file_get_contents($normal));
foreach (array('NUL', 'nul:', '\\\\.\\NUL') as $name) {
    $fp = @fopen($name, 'wb');
    var_dump(is_resource($fp));
    if (is_resource($fp)) fclose($fp);
}
foreach (array('NULL', 'CON1', 'COM10', 'LPT0', 'console.txt', 'x.nul', 'aux_') as $name) {
    $path = $dir . '/' . $name;
    var_dump(file_put_contents($path, 'ok') === 2 && file_get_contents($path) === 'ok');
    unlink($path);
}
unlink($normal);
rmdir($dir);
?>
--CLEAN--
<?php
$dir = dirname(__FILE__) . '/' . basename(__FILE__, '.clean.php') . '.dir';
if (is_dir($dir)) {
    foreach (glob($dir . '/*') as $file) unlink($file);
    rmdir($dir);
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
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
string(4) "safe"
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
