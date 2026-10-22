--TEST--
mysqlnd rejects truncated greeting, auth, OK, EOF and prepare packets on loopback
--SKIPIF--
<?php
if (!extension_loaded('mysqli') || strpos(mysqli_get_client_info(), 'mysqlnd') === false) die('skip mysqlnd mysqli required');
if (!function_exists('proc_open') || !function_exists('stream_socket_server')) die('skip local peer process support required');
?>
--FILE--
<?php
require __DIR__ . '/backport_mysqlnd_peer.inc';
backport_mysqlnd_run('packets');
?>
--EXPECTF--
packets: %d cases, 0 failures
