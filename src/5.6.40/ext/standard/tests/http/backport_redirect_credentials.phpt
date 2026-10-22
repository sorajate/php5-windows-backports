--TEST--
HTTP redirects preserve same-origin headers and strip every credential on port/hostname changes
--SKIPIF--
<?php
if (!function_exists('proc_open') || !function_exists('stream_socket_server')) die('skip process/socket fixture unavailable');
if (!ini_get('allow_url_fopen')) die('skip allow_url_fopen disabled');
if (gethostbyname('localhost') !== '127.0.0.1') die('skip localhost must resolve to IPv4 loopback');
?>
--FILE--
<?php
require __DIR__ . '/backport_redirect_server.inc';
foreach (array(false, true) as $as_array) {
    foreach (array('same', 'hostcase', 'port', 'host') as $mode) {
        $fixture = backport_http_start($mode);
        $headers = backport_http_headers($as_array);
        $context = stream_context_create(array('http' => array('header' => $headers, 'timeout' => 5)));
        $before = stream_context_get_options($context);
        $requests = json_decode(file_get_contents($fixture[2], false, $context), true);
        $same = $mode === 'same' || $mode === 'hostcase';
        $ok = is_array($requests) && count($requests) === 2
            && backport_http_has_credentials($requests[0]['raw'])
            && backport_http_has_controls($requests[0]['raw'])
            && backport_http_has_controls($requests[1]['raw'])
            && ($same ? backport_http_has_credentials($requests[1]['raw'])
                : strpos($requests[1]['raw'], 'secret-') === false)
            && stream_context_get_options($context) === $before;
        echo ($as_array ? 'array' : 'string'), ' ', $mode, ': ', $ok ? 'OK' : 'FAIL', "\n";
        backport_http_finish($fixture);
    }
}
?>
--EXPECT--
string same: OK
string hostcase: OK
string port: OK
string host: OK
array same: OK
array hostcase: OK
array port: OK
array host: OK
