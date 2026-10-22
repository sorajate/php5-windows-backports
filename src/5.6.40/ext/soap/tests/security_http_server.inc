<?php
/* A bounded, loopback-only HTTP fixture shared by SOAP security tests. */
function soap_security_http_start($mode) {
    $binary = PHP_BINARY;
    $command = escapeshellarg($binary) . ' -n ' . escapeshellarg(__FILE__)
        . ' soap-http-fixture ' . escapeshellarg($mode);
    $pipes = array();
    $process = proc_open($command, array(0 => array('pipe', 'r'),
        1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, null, null,
        array('bypass_shell' => true));
    if (!is_resource($process)) {
        throw new Exception('Cannot start HTTP fixture');
    }
    fclose($pipes[0]);
    $address = trim(fgets($pipes[1]));
    if (!preg_match('/^127\.0\.0\.1:[0-9]+$/', $address)) {
        throw new Exception('HTTP fixture failed: ' . stream_get_contents($pipes[2]));
    }
    return array($process, $pipes, 'http://' . $address . '/');
}

function soap_security_http_stop($fixture) {
    fclose($fixture[1][1]);
    $error = stream_get_contents($fixture[1][2]);
    fclose($fixture[1][2]);
    $status = proc_close($fixture[0]);
    if ($status != 0 || $error !== '') {
        throw new Exception('HTTP fixture failed: ' . $error);
    }
}

if (isset($argv[1]) && $argv[1] === 'soap-http-fixture') {
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$server) {
        exit(1);
    }
    echo stream_socket_get_name($server, false), "\n";
    flush();
    $connection = stream_socket_accept($server, 10);
    if (!$connection) {
        exit(2);
    }
    stream_set_timeout($connection, 5);
    $remaining = 0;
    while (($line = fgets($connection, 8192)) !== false && trim($line) !== '') {
        if (stripos($line, 'Content-Length:') === 0) {
            $remaining = (int) trim(substr($line, 15));
        }
    }
    /* These tests send short requests; refuse an accidentally unbounded body. */
    if ($remaining < 0 || $remaining > 65536) {
        exit(3);
    }
    while ($remaining > 0) {
        $part = fread($connection, $remaining);
        if ($part === false || $part === '') {
            exit(4);
        }
        $remaining -= strlen($part);
    }
    $body = '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"'
        . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
        . ' xmlns:xsd="http://www.w3.org/2001/XMLSchema"><s:Body>'
        . '<testResponse><return xsi:type="xsd:string">ok</return></testResponse>'
        . '</s:Body></s:Envelope>';
    $response = "HTTP/1.1 200 OK\r\nContent-Type: text/xml\r\nConnection: close\r\n";
    if ($argv[2] === 'chunked') {
        $response .= "Transfer-Encoding: chunked\r\n\r\n"
            . dechex(strlen($body)) . ";fixture=yes\r\n" . $body . "\r\n0\r\n\r\n";
    } else if ($argv[2] === 'oversized') {
        $response .= "Transfer-Encoding: chunked\r\n\r\n7fffffff\r\n";
    } else if ($argv[2] === 'wide') {
        $response .= "Transfer-Encoding: chunked\r\n\r\n10000000000000000\r\n";
    } else if ($argv[2] === 'close') {
        $response .= "\r\n" . $body;
    } else {
        $response .= 'Content-Length: ' . strlen($body) . "\r\n\r\n" . $body;
    }
    $offset = 0;
    $length = strlen($response);
    while ($offset < $length) {
        $sent = fwrite($connection, substr($response, $offset));
        if ($sent === false || $sent === 0) {
            exit(5);
        }
        $offset += $sent;
    }
    fclose($connection);
    fclose($server);
    exit(0);
}
