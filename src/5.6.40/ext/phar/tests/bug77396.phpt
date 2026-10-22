--TEST--
Bug #77396 Relative filename exceeding maximum path length causes null pointer dereference.
--SKIPIF--
<?php if (!extension_loaded("phar")) die("skip"); ?>
--FILE--
<?php
$path = '../' . str_repeat("x", PHP_MAXPATHLEN) . '.tar';
try {
    $phar = new PharData($path);
} catch (UnexpectedValueException $e) {
    echo get_class($e), ': ', $e->getMessage(), "\n";
}
?>
--EXPECT--
UnexpectedValueException: Phar creation or opening failed
