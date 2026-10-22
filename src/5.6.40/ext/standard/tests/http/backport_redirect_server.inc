<?php
/* Loopback-only redirect fixture; no pcntl dependency on Windows. */
function backport_http_start($mode) {
    $binary = PHP_BINARY;
    $command = escapeshellarg($binary) . ' -n ' . escapeshellarg(__FILE__)
        . ' backport-http-worker ' . escapeshellarg($mode);
    $pipes = array();
    $process = proc_open($command, array(0 => array('pipe', 'r'),
        1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, null, null,
        array('bypass_shell' => true));
    if (!is_resource($process)) throw new Exception('Cannot start redirect fixture');
    fclose($pipes[0]);
    $ports = json_decode(trim(fgets($pipes[1])), true);
    if (!is_array($ports) || count($ports) !== 2) {
        throw new Exception('Redirect fixture failed: ' . stream_get_contents($pipes[2]));
    }
    $host = $mode === 'hostcase' ? 'localhost' : '127.0.0.1';
    $scheme = $mode === 'proxy-direct' ? 'https' : 'http';
    return array($process, $pipes, $scheme . '://' . $host . ':' . $ports[0] . '/start',
        'http://127.0.0.1:' . $ports[0] . '/control', 'tcp://127.0.0.1:' . $ports[0]);
}

function backport_http_finish($fixture) {
    fclose($fixture[1][1]);
    $error = stream_get_contents($fixture[1][2]);
    fclose($fixture[1][2]);
    $status = proc_close($fixture[0]);
    if ($status !== 0 || $error !== '') throw new Exception('Redirect fixture failed (' . $status . '): ' . $error);
}

function backport_http_headers($as_array) {
    $headers = array(
        'X-Authorization: retained-prefix',
        'Authorization: secret-auth-one',
        'authorization: secret-auth-two',
        'Authorization \t: secret-auth-ows',
        "\tsecret-auth-fold",
        'Cookie: secret-cookie-one',
        'cookie \t: secret-cookie-ows',
        " secret-cookie-fold",
        'Proxy-Authorization: secret-proxy-one',
        'pRoXy-AuThOrIzAtIoN \t: secret-proxy-ows',
        "\tsecret-proxy-fold",
        " \tAuThOrIzAtIoN: secret-leading-ows",
        'X-Control: visible-control',
        "\tvisible-control-fold"
    );
    /* Keep actual horizontal whitespace in field-name OWS. */
    foreach ($headers as &$header) $header = str_replace('\\t', "\t", $header);
    unset($header);
    return $as_array ? $headers : implode("\r\n", $headers);
}

function backport_http_has_credentials($raw) {
    foreach (array('secret-auth-one', 'secret-auth-two', 'secret-auth-ows',
        'secret-auth-fold', 'secret-cookie-one', 'secret-cookie-ows',
        'secret-cookie-fold', 'secret-proxy-one', 'secret-proxy-ows',
        'secret-proxy-fold', 'secret-leading-ows') as $value) {
        if (strpos($raw, $value) === false) return false;
    }
    return true;
}

function backport_http_has_controls($raw) {
    return strpos($raw, 'X-Authorization: retained-prefix') !== false
        && strpos($raw, 'X-Control: visible-control') !== false
        && strpos($raw, 'visible-control-fold') !== false;
}

if (isset($argv[1]) && $argv[1] === 'backport-http-worker') {
    $servers = array();
    $ports = array();
    for ($i = 0; $i < 2; $i++) {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if (!$server) exit(1);
        $servers[] = $server;
        $address = stream_socket_get_name($server, false);
        $ports[] = (int) substr($address, strrpos($address, ':') + 1);
    }
    echo json_encode($ports), "\n";
    flush();
    $mode = $argv[2];
    if ($mode === 'proxy-direct') $plan = array(0);
    else if ($mode === 'roundtrip') $plan = array(0, 1, 0, 0);
    else if ($mode === 'port') $plan = array(0, 1);
    else $plan = array(0, 0);
    $requests = array();
    foreach ($plan as $index => $listener) {
        $connection = stream_socket_accept($servers[$listener], 5);
        if (!$connection) exit(2);
        stream_set_timeout($connection, 5);
        $raw = '';
        while (($line = fgets($connection, 8192)) !== false) {
            $raw .= $line;
            if (strlen($raw) > 65536) exit(3);
            if ($line === "\r\n" || $line === "\n") break;
        }
        if ($line === false) {
            fwrite(STDERR, 'Incomplete request: ' . bin2hex($raw) . "\n");
            exit(4);
        }
        $requests[] = array('listener' => $listener, 'raw' => $raw);
        if ($mode === 'proxy-direct' || ($mode === 'proxy-cross' && $index === 1)) {
            /* Reject the tunnel after capture; a TLS backend is unnecessary. */
            $response = "HTTP/1.0 502 Fixture Tunnel Rejected\r\nContent-Length: 0\r\nConnection: close\r\n\r\n";
        } else if ($index === 0 || ($mode === 'roundtrip' && $index === 1)) {
            $host = $mode === 'host' ? 'localhost' : ($mode === 'hostcase' ? 'LOCALHOST' : '127.0.0.1');
            $destination = ($mode === 'port' || $mode === 'roundtrip') && $index === 0 ? 1 : 0;
            $scheme = $mode === 'proxy-cross' ? 'https' : 'http';
            $location = $scheme . '://' . $host . ':' . $ports[$destination] . '/final';
            $response = "HTTP/1.0 302 Found\r\nLocation: " . $location
                . "\r\nContent-Length: 0\r\nConnection: close\r\n\r\n";
        } else {
            $body = json_encode($requests);
            $response = 'HTTP/1.0 200 OK' . "\r\nContent-Type: application/json\r\nContent-Length: "
                . strlen($body) . "\r\nConnection: close\r\n\r\n" . $body;
        }
        $offset = 0;
        while ($offset < strlen($response)) {
            $sent = fwrite($connection, substr($response, $offset));
            if (!$sent) exit(5);
            $offset += $sent;
        }
        fclose($connection);
    }
    foreach ($servers as $server) fclose($server);
    if ($mode === 'proxy-direct' || $mode === 'proxy-cross') {
        echo json_encode($requests), "\n";
    }
    exit(0);
}
