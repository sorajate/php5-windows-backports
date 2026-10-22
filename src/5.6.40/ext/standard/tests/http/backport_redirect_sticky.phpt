--TEST--
HTTP A-to-B-to-A redirects keep credentials stripped while the reusable context remains unchanged
--SKIPIF--
<?php
if (!function_exists('proc_open') || !function_exists('stream_socket_server')) die('skip process/socket fixture unavailable');
if (!ini_get('allow_url_fopen')) die('skip allow_url_fopen disabled');
?>
--FILE--
<?php
require __DIR__ . '/backport_redirect_server.inc';
foreach (array(false, true) as $as_array) {
    $fixture = backport_http_start('roundtrip');
    $context = stream_context_create(array('http' => array('header' => backport_http_headers($as_array), 'timeout' => 5)));
    $before = stream_context_get_options($context);
    $requests = json_decode(file_get_contents($fixture[2], false, $context), true);
    $ok = is_array($requests) && count($requests) === 3
        && backport_http_has_credentials($requests[0]['raw'])
        && strpos($requests[1]['raw'], 'secret-') === false
        && strpos($requests[2]['raw'], 'secret-') === false
        && backport_http_has_controls($requests[1]['raw'])
        && backport_http_has_controls($requests[2]['raw']);
    echo ($as_array ? 'array' : 'string'), ' sticky: ', $ok ? 'OK' : 'FAIL', "\n";
    $control = json_decode(file_get_contents($fixture[3], false, $context), true);
    $ok = is_array($control) && count($control) === 4
        && backport_http_has_credentials($control[3]['raw'])
        && backport_http_has_controls($control[3]['raw'])
        && stream_context_get_options($context) === $before;
    echo ($as_array ? 'array' : 'string'), ' reuse: ', $ok ? 'OK' : 'FAIL', "\n";
    backport_http_finish($fixture);
}
?>
--EXPECT--
string sticky: OK
string reuse: OK
array sticky: OK
array reuse: OK
