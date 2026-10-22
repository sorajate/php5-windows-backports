<?php
/* All traffic stays on an ephemeral IPv4 loopback listener. */
function backport_peer_spawn($certificates, $load_extension) {
    $binary = PHP_BINARY;
    $command = escapeshellarg($binary) . ' -n';
    if ($load_extension) {
        $extension = PHP_SHLIB_SUFFIX === 'dll' ? 'php_openssl.dll' : 'openssl.' . PHP_SHLIB_SUFFIX;
        $command .= ' -d ' . escapeshellarg('extension_dir=' . ini_get('extension_dir'))
            . ' -d ' . escapeshellarg('extension=' . $extension);
    }
    $command .= ' ' . escapeshellarg(__FILE__) . ' backport-peer-worker '
        . escapeshellarg(base64_encode(json_encode($certificates)));
    $pipes = array();
    $process = proc_open($command, array(0 => array('pipe', 'r'),
        1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, null, null,
        array('bypass_shell' => true));
    if (!is_resource($process)) throw new Exception('Cannot start TLS fixture');
    fclose($pipes[0]);
    return array($process, $pipes, trim(fgets($pipes[1])));
}

function backport_peer_start($certificates) {
    $fixture = backport_peer_spawn($certificates, false);
    if ($fixture[2] === 'OPENSSL-MISSING') {
        /* The test runner may load a shared extension with -d and no php.ini. */
        backport_peer_finish($fixture);
        $fixture = backport_peer_spawn($certificates, true);
    }
    if (!preg_match('/^127\.0\.0\.1:[0-9]+$/', $fixture[2])) {
        throw new Exception('TLS fixture failed: ' . stream_get_contents($fixture[1][2]));
    }
    return $fixture;
}

function backport_peer_finish($fixture) {
    fclose($fixture[1][1]);
    $error = stream_get_contents($fixture[1][2]);
    fclose($fixture[1][2]);
    $status = proc_close($fixture[0]);
    if ($status !== 0 || $error !== '') throw new Exception('TLS fixture failed: ' . $error);
}

function backport_peer_certificate($label, $common_name, $section, $key, $private_pem) {
    $arguments = array('config' => __DIR__ . '/backport_peer_names.cnf',
        'digest_alg' => 'sha256', 'x509_extensions' => $section);
    $csr = openssl_csr_new(array('commonName' => $common_name), $key, $arguments);
    $certificate = $csr ? openssl_csr_sign($csr, null, $key, 2, $arguments) : false;
    $pem = '';
    if (!$certificate || !openssl_x509_export($certificate, $pem)) {
        throw new Exception('Cannot generate TLS certificate ' . $label);
    }
    $parsed = openssl_x509_parse($certificate);
    $has_san = isset($parsed['extensions']['subjectAltName']);
    if ($has_san !== ($section !== 'backport_none') || $parsed['subject']['CN'] !== $common_name) {
        throw new Exception('Unexpected TLS fixture identity ' . $label);
    }
    $file = __DIR__ . '/backport_peer_fixture_' . $label . '.pem';
    if (file_put_contents($file, $pem . $private_pem) === false) {
        throw new Exception('Cannot write TLS fixture ' . $label);
    }
    return $file;
}

if (isset($argv[1]) && $argv[1] === 'backport-peer-worker') {
    if (!extension_loaded('openssl')) {
        echo "OPENSSL-MISSING\n";
        exit(0);
    }
    $certificates = json_decode(base64_decode($argv[2]), true);
    if (!is_array($certificates) || count($certificates) > 16) exit(1);
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$server) exit(2);
    echo stream_socket_get_name($server, false), "\n";
    flush();
    foreach ($certificates as $certificate) {
        $connection = stream_socket_accept($server, 5);
        if (!$connection) exit(3);
        stream_set_timeout($connection, 5);
        stream_context_set_option($connection, 'ssl', 'local_cert', $certificate);
        stream_context_set_option($connection, 'ssl', 'verify_peer', false);
        stream_context_set_option($connection, 'ssl', 'verify_peer_name', false);
        if (@stream_socket_enable_crypto($connection, true, STREAM_CRYPTO_METHOD_SSLv23_SERVER)) {
            @fwrite($connection, "ok\n");
        }
        fclose($connection);
    }
    fclose($server);
    exit(0);
}
