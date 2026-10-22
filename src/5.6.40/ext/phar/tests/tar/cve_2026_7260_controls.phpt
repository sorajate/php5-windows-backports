--TEST--
CVE-2026-7260: valid TAR link chains, relative aliases, repeated reads and write separation remain usable
--SKIPIF--
<?php if (!extension_loaded('phar')) die('skip phar required'); ?>
--INI--
phar.require_hash=0
phar.readonly=0
--FILE--
<?php
require dirname(__FILE__) . '/files/security_tar_helpers.inc';
$prefix = dirname(__FILE__) . '/' . basename(__FILE__, '.php');
$file = $prefix . '.tar';
$tar = phar_security_tar_entry('data', 'abcdef');
$tar .= phar_security_tar_entry('dir/local', 'local');
$links = array('direct' => 'data', 'chain' => 'direct', 'absolute' => '/data',
    'dir/relative' => '../data', 'dir/same' => './local', 'dir/precedence' => 'data',
    'writable' => 'data', 'appendable' => 'data', 'renameable' => 'chain');
foreach ($links as $name => $target) $tar .= phar_security_tar_entry($name, '', '2', $target);
for ($i = 0; $i < 128; ++$i) {
    $tar .= phar_security_tar_entry('long' . $i, '', '2', $i == 127 ? 'data' : 'long' . ($i + 1));
}
file_put_contents($file, $tar . str_repeat("\0", 1024));
$p = new PharData($file);
foreach (array('direct', 'chain', 'absolute', 'dir/relative', 'dir/precedence', 'long0') as $name) {
    var_dump($p[$name]->getContent() === 'abcdef');
    var_dump($p[$name]->getContent() === 'abcdef');
}
var_dump($p['dir/same']->getContent());
var_dump($p['dir/same']->getContent());
$url = 'phar://' . $file . '/';
$fp = fopen($url . 'chain', 'r');
var_dump(fseek($fp, 2));
var_dump(fread($fp, 2));
var_dump(fseek($fp, -2, SEEK_END));
var_dump(fread($fp, 2));
fclose($fp);
var_dump($p->copy('chain', 'copied'));
var_dump($p['copied']->getContent());
/* PHP 5's stream wrapper rejects writes/rename for a data-only .tar. */
$fp = @fopen($url . 'writable', 'r+');
var_dump($fp === false);
if (is_resource($fp)) fclose($fp);
var_dump($p['writable']->getContent());
var_dump($p['data']->getContent());
$warning = '';
set_error_handler(function ($number, $message) use (&$warning) { $warning = $message; return true; });
$fp = fopen($url . 'appendable', 'a');
restore_error_handler();
var_dump($fp === false && strpos($warning, 'append not supported') !== false);
if (is_resource($fp)) fclose($fp);
var_dump($p['appendable']->getContent());
var_dump($p['data']->getContent());
var_dump(@rename($url . 'renameable', $url . 'renamed') === false);
var_dump($p['renameable']->getContent());
/* Nontruncating writes and rename are supported for executable .phar.tar. */
$writefile = $prefix . '.write.phar.tar';
file_put_contents($writefile,
    phar_security_tar_entry('.phar/stub.php', '<?php __HALT_COMPILER(); ?>')
    . phar_security_tar_entry('data', 'abcdef')
    . phar_security_tar_entry('writable', '', '2', 'data')
    . phar_security_tar_entry('renameable', '', '2', 'data') . str_repeat("\0", 1024));
$w = new Phar($writefile);
$writeurl = 'phar://' . str_replace('\\', '/', $writefile) . '/';
$fp = fopen($writeurl . 'writable', 'r+');
var_dump(is_resource($fp));
if (is_resource($fp)) {
    var_dump(stream_get_contents($fp));
    rewind($fp);
    fwrite($fp, 'Z');
    fclose($fp);
}
var_dump($w['writable']->getContent());
var_dump($w['data']->getContent());
$renamed = rename($writeurl . 'renameable', $writeurl . 'renamed');
var_dump($renamed);
if ($renamed) var_dump($w['renamed']->getContent());
/* A separate normal TAR with a hard link keeps the legacy conversion path covered. */
$other = $prefix . '.convert.tar';
file_put_contents($other, phar_security_tar_entry('data', 'ok')
    . phar_security_tar_entry('hard', '', '1', 'data') . str_repeat("\0", 1024));
$q = new PharData($other);
$converted = $q->convertToData(Phar::TAR, 0, '.converted.tar');
var_dump($converted['data']->getContent());
var_dump($converted['hard']->getContent());
?>
--CLEAN--
<?php
$prefix = dirname(__FILE__) . '/' . basename(__FILE__, '.clean.php');
foreach (array_merge(array($prefix . '.tar'), glob($prefix . '.*.tar')) as $file) {
    if (file_exists($file)) unlink($file);
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
string(5) "local"
string(5) "local"
int(0)
string(2) "cd"
int(0)
string(2) "ef"
bool(true)
string(6) "abcdef"
bool(true)
string(6) "abcdef"
string(6) "abcdef"
bool(true)
string(6) "abcdef"
string(6) "abcdef"
bool(true)
string(6) "abcdef"
bool(true)
string(6) "abcdef"
string(6) "Zbcdef"
string(6) "abcdef"
bool(true)
string(6) "abcdef"
string(2) "ok"
string(2) "ok"
