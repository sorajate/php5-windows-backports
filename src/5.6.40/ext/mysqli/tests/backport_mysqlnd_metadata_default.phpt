--TEST--
mysqlnd bounds metadata names and defaults, rejects wrong fixed-block markers, and preserves NULL, empty and length encodings
--SKIPIF--
<?php
if (!extension_loaded('mysqli') || strpos(mysqli_get_client_info(), 'mysqlnd') === false) die('skip mysqlnd mysqli required');
if (!function_exists('proc_open') || !function_exists('stream_socket_server')) die('skip local peer process support required');
?>
--FILE--
<?php
require __DIR__ . '/backport_mysqlnd_peer.inc';
backport_mysqlnd_run('metadata');
?>
--EXPECT--
metadata: 45/45 cases, 0 failures
