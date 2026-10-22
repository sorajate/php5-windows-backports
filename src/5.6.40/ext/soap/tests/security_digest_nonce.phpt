--TEST--
SOAP Digest authentication uses the full secure nonce in the header and MD5/MD5-sess response
--SKIPIF--
<?php
require_once('skipif.inc');
if (!function_exists('proc_open') || !function_exists('stream_socket_server')) die('skip process/socket fixture unavailable');
?>
--FILE--
<?php
require __DIR__ . '/security_http_server.inc';
$nonces = array();
foreach (array('MD5', 'MD5-sess') as $algorithm) {
    $fixture = soap_security_http_start('length');
    $client = new SoapClient(null, array('uri' => 'urn:test', 'location' => $fixture[2],
        'login' => 'user', 'password' => 'password', 'trace' => true));
    $client->_digest = array('realm' => 'fixture', 'nonce' => 'server-nonce',
        'qop' => 'auth', 'algorithm' => $algorithm);
    var_dump($client->__soapCall('test', array()) === 'ok');
    preg_match('/cnonce="([0-9a-f]+)"/', $client->__getLastRequestHeaders(), $cnonce);
    preg_match('/response="([0-9a-f]+)"/', $client->__getLastRequestHeaders(), $response);
    var_dump(isset($cnonce[1]) && strlen($cnonce[1]) === 32);
    $ha1 = md5('user:fixture:password');
    if ($algorithm === 'MD5-sess') {
        $ha1 = md5($ha1 . ':server-nonce:' . $cnonce[1]);
    }
    $expected = md5($ha1 . ':server-nonce:00000001:' . $cnonce[1] . ':auth:' . md5('POST:/'));
    var_dump(isset($response[1]) && $response[1] === $expected);
    $nonces[] = $cnonce[1];
    soap_security_http_stop($fixture);
}
var_dump($nonces[0] !== $nonces[1]);
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
