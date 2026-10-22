--TEST--
SOAP decoding bounds deep values, href chains and recursive maps while allowing ordinary values after errors
--SKIPIF--
<?php require_once('skipif.inc'); ?>
--FILE--
<?php
class SecurityDepthClient extends SoapClient {
    public $content;
    function __doRequest($request, $location, $action, $version, $one_way = 0) {
        return '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"'
            . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            . ' xmlns:a="http://xml.apache.org/xml-soap"><s:Body>'
            . '<testResponse>' . $this->content . '</testResponse></s:Body></s:Envelope>';
    }
}
$client = new SecurityDepthClient(null, array('uri' => 'urn:test', 'location' => 'http://unused/'));
$chain = '<return href="#n0"/>';
for ($i = 0; $i < 600; $i++) {
    /* Typed maps force recursive decoding; PHP 5 does not chase an untyped
       chain of empty href-only elements through every hop. */
    $chain .= '<node id="n' . $i . '" xsi:type="a:Map"><item><key>next</key>'
        . '<value href="#n' . ($i + 1) . '"/></item></node>';
}
$chain .= '<node id="n600">end</node>';
$cases = array(
    '<return>' . str_repeat('<value>', 600) . 'end' . str_repeat('</value>', 600) . '</return>',
    $chain,
    '<return href="#loop"/><map id="loop" xsi:type="a:Map">'
        . '<item><key>self</key><value href="#loop"/></item></map>'
);
foreach ($cases as $content) {
    $client->content = $content;
    try {
        $client->__soapCall('test', array());
        echo "unexpected success\n";
    } catch (SoapFault $fault) {
        echo strpos($fault->getMessage(), 'Nesting level too deep') !== false ? "bounded\n" : $fault->getMessage() . "\n";
    }
    $client->content = '<return>ordinary</return>';
    var_dump($client->__soapCall('test', array()) === 'ordinary');
}
?>
--EXPECT--
bounded
bool(true)
bounded
bool(true)
bounded
bool(true)
