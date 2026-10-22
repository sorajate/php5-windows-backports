--TEST--
CVE-2026-7260: cyclic, aliased and dangling TAR links fail without recursion or raw-data fallback
--SKIPIF--
<?php if (!extension_loaded('phar')) die('skip phar required'); ?>
--INI--
phar.require_hash=0
phar.readonly=0
--FILE--
<?php
require dirname(__FILE__) . '/files/security_tar_helpers.inc';
$prefix = dirname(__FILE__) . '/' . basename(__FILE__, '.php');
$cases = array(
    array('self' => 'self'),
    array('a' => 'b', 'b' => 'a'),
    array('tail' => 'a', 'a' => 'b', 'b' => 'a'),
    array('dir/self' => './self'),
    array('dir/a' => './b', 'dir/b' => '../dir/a'),
    array('absolute' => '/absolute'),
    array('dangling' => 'missing')
);
foreach ($cases as $i => $links) {
    $file = $prefix . '.' . $i . '.tar';
    $tar = phar_security_tar_entry('control', 'safe');
    foreach ($links as $name => $target) {
        $tar .= phar_security_tar_entry($name, '', '2', $target);
    }
    file_put_contents($file, $tar . str_repeat("\0", 1024));
    $p = new PharData($file);
    reset($links);
    $name = key($links);
    try {
        $p[$name]->getContent();
        echo "accepted invalid contents\n";
    } catch (Exception $e) {
        echo strpos($e->getMessage(), 'invalid or circular link') !== false ? "invalid link\n" : $e->getMessage() . "\n";
    }
    $url = 'phar://' . $file . '/' . $name;
    var_dump(@file_get_contents($url) === false);
    var_dump(@fopen($url, 'r+') === false);
    var_dump(@fopen($url, 'a') === false);
    try {
        $p->copy($name, 'copy');
        echo "copied invalid link\n";
    } catch (Exception $e) {
        echo strpos($e->getMessage(), 'invalid or circular link') !== false ? "copy refused\n" : $e->getMessage() . "\n";
    }
    var_dump(!isset($p['copy']));
    var_dump(@rename($url, 'phar://' . $file . '/renamed') === false);
    var_dump($p['control']->getContent());
    try {
        $p->convertToData(Phar::ZIP);
        echo "converted invalid link\n";
    } catch (Exception $e) {
        echo strpos($e->getMessage(), 'invalid or circular link') !== false ? "conversion refused\n" : $e->getMessage() . "\n";
    }
}
/* Reach the writable stream and rename paths supported by PHP 5 using an
 * executable archive, rather than its read-only .tar stream-wrapper path. */
$file = $prefix . '.write.phar.tar';
file_put_contents($file, phar_security_tar_entry('.phar/stub.php', '<?php __HALT_COMPILER(); ?>')
    . phar_security_tar_entry('control', 'safe')
    . phar_security_tar_entry('a', '', '2', 'b')
    . phar_security_tar_entry('b', '', '2', 'a') . str_repeat("\0", 1024));
$p = new Phar($file);
$url = 'phar://' . str_replace('\\', '/', $file) . '/';
var_dump(@fopen($url . 'a', 'r+') === false);
var_dump(@rename($url . 'a', $url . 'renamed') === false);
var_dump($p['control']->getContent());
?>
--CLEAN--
<?php
$prefix = dirname(__FILE__) . '/' . basename(__FILE__, '.clean.php');
foreach (array_merge(glob($prefix . '.*.tar'), glob($prefix . '.*.zip')) as $file) unlink($file);
?>
--EXPECT--
invalid link
bool(true)
bool(true)
bool(true)
copy refused
bool(true)
bool(true)
string(4) "safe"
conversion refused
invalid link
bool(true)
bool(true)
bool(true)
copy refused
bool(true)
bool(true)
string(4) "safe"
conversion refused
invalid link
bool(true)
bool(true)
bool(true)
copy refused
bool(true)
bool(true)
string(4) "safe"
conversion refused
invalid link
bool(true)
bool(true)
bool(true)
copy refused
bool(true)
bool(true)
string(4) "safe"
conversion refused
invalid link
bool(true)
bool(true)
bool(true)
copy refused
bool(true)
bool(true)
string(4) "safe"
conversion refused
invalid link
bool(true)
bool(true)
bool(true)
copy refused
bool(true)
bool(true)
string(4) "safe"
conversion refused
invalid link
bool(true)
bool(true)
bool(true)
copy refused
bool(true)
bool(true)
string(4) "safe"
conversion refused
bool(true)
bool(true)
string(4) "safe"
