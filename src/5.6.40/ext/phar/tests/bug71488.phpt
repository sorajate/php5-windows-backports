--TEST--
Phar: bug #71488: Stack overflow when decompressing tar archives
--SKIPIF--
<?php if (!extension_loaded("phar")) die("skip"); ?>
--FILE--
<?php
$p = new PharData(__DIR__."/bug71488.tar");
/* The malformed link is now rejected before TAR output is attempted. */
try {
    $newp = $p->decompress("test");
} catch (UnexpectedValueException $e) {
    echo strpos($e->getMessage(), 'invalid or circular link') !== false
        ? "Invalid archive link refused\n" : $e->getMessage() . "\n";
}
?>

DONE
--CLEAN--
<?php
@unlink(__DIR__."/bug71488.test");
?>
--EXPECT--
Invalid archive link refused

DONE
