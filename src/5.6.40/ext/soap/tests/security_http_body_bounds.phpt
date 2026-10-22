--TEST--
SOAP HTTP body growth rejects oversized chunks before allocation and preserves ordinary framing
--SKIPIF--
<?php
require_once('skipif.inc');
if (!function_exists('proc_open') || !function_exists('stream_socket_server')) die('skip process/socket fixture unavailable');
?>
--INI--
memory_limit=32M
--FILE--
<?php
require __DIR__ . '/security_http_server.inc';
foreach (array('length', 'chunked', 'close', 'oversized', 'wide') as $mode) {
    $fixture = soap_security_http_start($mode);
    $client = new SoapClient(null, array('uri' => 'urn:test', 'location' => $fixture[2]));
    try {
        $result = $client->__soapCall('test', array());
        echo $mode, ': ', $result, "\n";
    } catch (SoapFault $fault) {
        echo $mode, ': ', $fault->faultcode, "\n";
    }
    soap_security_http_stop($fixture);
}
?>
--EXPECT--
length: ok
chunked: ok
close: ok
oversized: HTTP
wide: HTTP
