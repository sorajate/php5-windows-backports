--TEST--
SOAP decoder owns overwritten map objects and preserves reference identity, cycles, classmap and encoder references
--SKIPIF--
<?php require_once('skipif.inc'); ?>
--FILE--
<?php
class SecurityRefClient extends SoapClient {
    public $response;
    public $request;
    function __doRequest($request, $location, $action, $version, $one_way = 0) {
        $this->request = $request;
        return $this->response;
    }
}
class SecurityRefObject {}
function security_ref_envelope($version, $content) {
    $env = $version === SOAP_1_1 ? 'http://schemas.xmlsoap.org/soap/envelope/' : 'http://www.w3.org/2003/05/soap-envelope';
    $enc = $version === SOAP_1_1 ? 'http://schemas.xmlsoap.org/soap/encoding/' : 'http://www.w3.org/2003/05/soap-encoding';
    return '<s:Envelope xmlns:s="' . $env . '" xmlns:e="' . $enc . '"'
        . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
        . ' xmlns:xsd="http://www.w3.org/2001/XMLSchema"'
        . ' xmlns:a="http://xml.apache.org/xml-soap"><s:Body>'
        . '<testResponse>' . $content . '</testResponse></s:Body></s:Envelope>';
}
foreach (array(SOAP_1_1, SOAP_1_2) as $version) {
    $id = $version === SOAP_1_1 ? 'id="old"' : 'e:id="old"';
    $ref = $version === SOAP_1_1 ? 'href="#old"' : 'e:ref="#old"';
    $client = new SecurityRefClient(null, array('uri' => 'urn:test', 'location' => 'http://unused/',
        'soap_version' => $version, 'classmap' => array('Struct' => 'SecurityRefObject')));
    $client->response = security_ref_envelope($version,
        '<return xsi:type="a:Map">'
        . '<item><key>same</key><value ' . $id . ' xsi:type="e:Struct">'
        . '<marker>original</marker><self ' . $ref . '/></value></item>'
        . '<item><key>same</key><value xsi:type="xsd:string">last</value></item>'
        . '<item><key>alias</key><value ' . $ref . '/></item>'
        . '<item><key>alias2</key><value ' . $ref . '/></item>'
        . '<item><key xsi:type="xsd:int">7</key><value>first</value></item>'
        . '<item><key xsi:type="xsd:int">7</key><value>last numeric</value></item>'
        . '</return>');
    $result = $client->__soapCall('test', array());
    var_dump($result['same'] === 'last' && $result[7] === 'last numeric');
    var_dump($result['alias'] instanceof SecurityRefObject && $result['alias']->marker === 'original');
    var_dump($result['alias'] === $result['alias2'] && $result['alias']->self === $result['alias']);
    /* The encoder map must continue to borrow XML nodes without a zval destructor. */
    $client->response = security_ref_envelope($version, '<return xsi:type="xsd:string">ok</return>');
    $object = new stdClass;
    $object->value = 'control';
    var_dump($client->__soapCall('test', array($object, $object)) === 'ok');
    var_dump((bool) preg_match('/(?:href|[a-zA-Z0-9_-]+:ref)="#ref[0-9]+"/', $client->request));
    /* Flattening a single response must not free a zval still held by the map. */
    $client->response = security_ref_envelope($version, '<return ' . $id
        . ' xsi:type="e:Struct"><marker>root</marker><self ' . $ref . '/></return>');
    $root = $client->__soapCall('test', array());
    var_dump($root instanceof SecurityRefObject && $root->marker === 'root' && $root->self === $root);
}
function security_ref_from_xml($xml) {
    return strpos($xml, 'payload') !== false ? 'mapped' : 'unexpected';
}
$client = new SecurityRefClient(null, array('uri' => 'urn:test', 'location' => 'http://unused/',
    'typemap' => array(array('type_name' => 'token', 'type_ns' => 'urn:types',
        'from_xml' => 'security_ref_from_xml'))));
$client->response = security_ref_envelope(SOAP_1_1,
    '<return xmlns:t="urn:types" xsi:type="t:token">payload</return>');
var_dump($client->__soapCall('test', array()) === 'mapped');
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
