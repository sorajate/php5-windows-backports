--TEST--
HTTP redirect crossing to HTTPS does not restore proxy credentials in CONNECT
--SKIPIF--
<?php
if (!extension_loaded('openssl')) die('skip openssl extension unavailable');
if (!function_exists('proc_open') || !function_exists('stream_socket_server')) die('skip process/socket fixture unavailable');
if (!ini_get('allow_url_fopen')) die('skip allow_url_fopen disabled');
?>
--FILE--
<?php
require __DIR__ . '/backport_redirect_server.inc';
foreach (array(false, true) as $as_array) {
    foreach (array('proxy-direct', 'proxy-cross') as $mode) {
        $fixture = backport_http_start($mode);
        $context = stream_context_create(array('http' => array(
            'header' => backport_http_headers($as_array), 'proxy' => $fixture[4], 'timeout' => 5),
            'ssl' => array('peer_name' => '127.0.0.1')));
        $before = stream_context_get_options($context);
        /* The fixture rejects CONNECT after recording it; no TLS connection follows. */
        @file_get_contents($fixture[2], false, $context);
        $requests = json_decode(trim(fgets($fixture[1][1])), true);
        $last = is_array($requests) ? end($requests) : false;
        $ok = $last && count($requests) === ($mode === 'proxy-direct' ? 1 : 2)
            && ($mode === 'proxy-direct' || backport_http_has_credentials($requests[0]['raw']))
            && strpos($last['raw'], 'CONNECT ') === 0
            && ($mode === 'proxy-direct'
                ? strpos($last['raw'], 'secret-proxy-one') !== false
                : strpos($last['raw'], 'secret-') === false)
            && stream_context_get_options($context) === $before;
        echo ($as_array ? 'array' : 'string'), ' ', $mode, ': ', $ok ? 'OK' : 'FAIL', "\n";
        backport_http_finish($fixture);
    }
}
?>
--EXPECT--
string proxy-direct: OK
string proxy-cross: OK
array proxy-direct: OK
array proxy-cross: OK
