--TEST--
SOAP XML cleanup and reference search traverse deep trees without recursive C calls
--SKIPIF--
<?php require_once('skipif.inc'); ?>
--INI--
soap.wsdl_cache_enabled=0
--FILE--
<?php
/* File parsing uses cleanup; documentation must not alter the WSDL API. */
$source = __DIR__ . '/server030.wsdl';
$file = __DIR__ . '/security_xml_traversal.wsdl';
$control = new SoapClient($source);
$documentation = '<documentation>' . str_repeat("<n xmlns=\"urn:documentation\"> \n<!-- comment -->", 4096)
    . 'preserved text' . str_repeat('</n>', 4096) . '</documentation>';
file_put_contents($file, str_replace('<types>', $documentation . '<types>', file_get_contents($source)));
$deep = new SoapClient($file);
var_dump(count($control->__getFunctions()) > 0 && $deep->__getFunctions() === $control->__getFunctions());
unlink($file);

/* Memory parsing keeps its existing semantics; an ignored header is searched for an id. */
class SecurityTreeClient extends SoapClient {
    function __doRequest($request, $location, $action, $version, $one_way = 0) {
        return '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" xmlns:h="urn:header">'
            . '<s:Header><h:ignored>' . str_repeat('<h:branch>', 4096)
            . '<h:target id="wanted">control</h:target>' . str_repeat('</h:branch>', 4096)
            . '</h:ignored></s:Header><s:Body><testResponse><return href="#wanted"/>'
            . '</testResponse></s:Body></s:Envelope>';
    }
}
$client = new SecurityTreeClient(null, array('uri' => 'urn:test', 'location' => 'http://unused/'));
var_dump($client->__soapCall('test', array()) === 'control');
?>
--CLEAN--
<?php @unlink(__DIR__ . '/security_xml_traversal.wsdl'); ?>
--EXPECT--
bool(true)
bool(true)
