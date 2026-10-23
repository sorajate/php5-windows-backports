--TEST--
mysqlnd bounds text and prepared rows before field codecs, including temporal and numeric values, and rejects trailing row bytes
--SKIPIF--
<?php
if (!extension_loaded('mysqli') || strpos(mysqli_get_client_info(), 'mysqlnd') === false) die('skip mysqlnd mysqli required');
if (!function_exists('proc_open') || !function_exists('stream_socket_server')) die('skip local peer process support required');
?>
--FILE--
<?php
require __DIR__ . '/backport_mysqlnd_peer.inc';
backport_mysqlnd_run('rows');
?>
--EXPECT--
rows: 184/184 cases, 0 failures
