--TEST--
mysqlnd loopback suite via procedural mysqli_connect (nonpersistent handle) with pinned case counts
--SKIPIF--
<?php
if (!extension_loaded('mysqli') || strpos(mysqli_get_client_info(), 'mysqlnd') === false) die('skip mysqlnd mysqli required');
if (!function_exists('proc_open') || !function_exists('stream_socket_server')) die('skip local peer process support required');
?>
--FILE--
<?php
require __DIR__ . '/backport_mysqlnd_peer.inc';
/* mysqli_connect() allocates the mysqlnd handle non-persistent while
 * mysqli_init() always allocates it persistently; the metadata cleanup
 * paths must release results exactly once in both modes. */
putenv('PHP_BACKPORT_MYSQLND_DIRECT_CONNECT=1');
echo 'mode=', getenv('PHP_BACKPORT_MYSQLND_DIRECT_CONNECT') ? 'direct' : 'init', "\n";
backport_mysqlnd_run('metadata');
backport_mysqlnd_run('rows');
?>
--EXPECT--
mode=direct
metadata: 45/45 cases, 0 failures
rows: 184/184 cases, 0 failures
