--TEST--
TLS peer identity obeys SAN precedence and checks wildcard prefix/suffix bounds
--SKIPIF--
<?php
if (!extension_loaded('openssl')) die('skip openssl extension unavailable');
if (!function_exists('proc_open') || !function_exists('stream_socket_server')) die('skip process/socket fixture unavailable');
?>
--FILE--
<?php
require __DIR__ . '/backport_peer_server.inc';
/* Reuse the upstream public test key; make fresh certificates to avoid expiry. */
$private = file_get_contents(__DIR__ . '/private_rsa_2048.key');
$key = openssl_pkey_get_private($private);
if (!$key) throw new Exception('Cannot load upstream test key');
$definitions = array(
    'dns' => array('cn-only.test', 'backport_dns'),
    'ip' => array('cn-only.test', 'backport_ip'),
    'none' => array('cn-only.test', 'backport_none'),
    'wild' => array('*.example.test', 'backport_none'),
    'partial' => array('foo*.example.test', 'backport_none'),
    'prefix' => array('averylongliteralprefix*.test', 'backport_none'),
    'suffix' => array('*.averylongsuffix.example.test', 'backport_none'),
    'overlap' => array('foo*oo', 'backport_none')
);
$files = array();
foreach ($definitions as $label => $definition) {
    $files[$label] = backport_peer_certificate($label, $definition[0], $definition[1], $key, $private);
}
$cases = array(
    array('SAN mismatch does not use CN', 'dns', 'cn-only.test', false),
    array('matching DNS SAN', 'dns', 'san-only.test', true),
    array('IP SAN does not use CN', 'ip', 'cn-only.test', false),
    array('matching IP SAN', 'ip', '127.0.0.1', true),
    array('CN without SAN', 'none', 'cn-only.test', true),
    array('ordinary wildcard', 'wild', 'one.example.test', true),
    array('wildcard cannot cross labels', 'wild', 'two.one.example.test', false),
    array('literal wildcard prefix', 'partial', 'foobar.example.test', true),
    array('prefix longer than hostname', 'prefix', 'x.test', false),
    array('suffix longer than hostname', 'suffix', 'x.test', false),
    array('overlapping prefix and suffix', 'overlap', 'fooo', false)
);
$certificates = array();
foreach ($cases as $case) $certificates[] = $files[$case[1]];
$fixture = backport_peer_start($certificates);
foreach ($cases as $case) {
    $context = stream_context_create(array('ssl' => array(
        'verify_peer' => false, 'verify_peer_name' => true,
        'peer_name' => $case[2], 'SNI_enabled' => false,
        'crypto_method' => STREAM_CRYPTO_METHOD_SSLv23_CLIENT
    )));
    $connection = @stream_socket_client('ssl://' . $fixture[2], $errno, $error,
        5, STREAM_CLIENT_CONNECT, $context);
    $accepted = is_resource($connection);
    $body_ok = true;
    if ($accepted) {
        stream_set_timeout($connection, 5);
        $body_ok = fgets($connection) === "ok\n";
        fclose($connection);
    }
    echo $case[0], ': ', $accepted === $case[3] && $body_ok ? 'OK' : 'FAIL', "\n";
}
backport_peer_finish($fixture);
foreach ($files as $file) unlink($file);
?>
--CLEAN--
<?php
foreach (array('dns', 'ip', 'none', 'wild', 'partial', 'prefix', 'suffix', 'overlap') as $label) {
    @unlink(__DIR__ . '/backport_peer_fixture_' . $label . '.pem');
}
?>
--EXPECT--
SAN mismatch does not use CN: OK
matching DNS SAN: OK
IP SAN does not use CN: OK
matching IP SAN: OK
CN without SAN: OK
ordinary wildcard: OK
wildcard cannot cross labels: OK
literal wildcard prefix: OK
prefix longer than hostname: OK
suffix longer than hostname: OK
overlapping prefix and suffix: OK
