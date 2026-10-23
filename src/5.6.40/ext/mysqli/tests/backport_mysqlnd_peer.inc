<?php
/* A single-use, bounded loopback peer. No database or external network needed. */
function backport_mysqlnd_len($value, $form = 1) {
    if ($form === 3) return "\xfc" . pack('v', $value);
    if ($form === 4) return "\xfd" . substr(pack('V', $value), 0, 3);
    if ($form === 9) return "\xfe" . pack('V2', $value, 0);
    return chr($value);
}

function backport_mysqlnd_greeting() {
    return "\x0a5.6.99-backport\0" . pack('V', 1) . "12345678\0"
        . pack('v', 0x8201) . "\x21" . pack('v', 2) . pack('v', 8)
        . "\x15" . str_repeat("\0", 10) . "abcdefghijkl\0mysql_native_password\0";
}

function backport_mysqlnd_field($type = 253, $default = '', $name_form = 1, $width = 64, $marker = "\x0c") {
    $packet = '';
    foreach (array('def', 'test', 't', 't', 'v', 'v') as $name) {
        $packet .= backport_mysqlnd_len(strlen($name), $name_form) . $name;
    }
    return $packet . $marker . pack('v', 33) . pack('V', $width) . chr($type)
        . pack('v', 0) . "\0\0\0" . $default;
}

function backport_mysqlnd_cases($group) {
    $cases = array();
    $ok = "\0\0\0\x02\0\0\0";
    if ($group === 'packets') {
        $greet = backport_mysqlnd_greeting();
        $base = strpos($greet, 'abcdefghijkl');
        foreach (array(0, 1, 3, 18, 21, 26, 29, 30, 32, 35, $base - 1, $base + 1, $base + 12) as $cut) {
            $cases['greet-cut-' . $cut] = array('mode' => 'greet', 'payload' => substr($greet, 0, $cut));
        }
        $cases['greet-unterminated-full-buffer'] = array('mode' => 'greet', 'payload' => "\x0a" . str_repeat('x', 2047));
        $extended = $greet;
        $extended[$base - 11] = chr(250);
        $cases['greet-truncated-extended-salt'] = array('mode' => 'greet', 'payload' => $extended);
        foreach (array('auth', 'query', 'ping') as $mode) {
            for ($cut = 0; $cut < 7; $cut++) {
                $cases[$mode . '-fixed-' . $cut] = array('mode' => $mode, 'payload' => substr($ok, 0, $cut));
            }
            foreach (array(3, 4, 9) as $form) {
                $prefix = backport_mysqlnd_len(1, $form);
                for ($cut = 1; $cut < $form; $cut++) {
                    $cases[$mode . '-lenenc-' . $form . '-' . $cut] = array('mode' => $mode, 'payload' => "\0" . substr($prefix, 0, $cut));
                    $cases[$mode . '-insert-lenenc-' . $form . '-' . $cut] = array('mode' => $mode, 'payload' => "\0\0" . substr($prefix, 0, $cut));
                    $cases[$mode . '-message-lenenc-' . $form . '-' . $cut] = array('mode' => $mode, 'payload' => $ok . substr($prefix, 0, $cut));
                }
            }
            $cases[$mode . '-message-overrun'] = array('mode' => $mode, 'payload' => $ok . "\x20x");
            $cases[$mode . '-control'] = array('mode' => $mode, 'payload' => $ok, 'valid' => true);
            foreach (array(1, 3, 4, 9) as $form) {
                $cases[$mode . '-lenenc-control-' . $form] = array('mode' => $mode, 'payload' => "\0" . backport_mysqlnd_len(1, $form) . "\0\x02\0\0\0", 'valid' => true);
            }
        }
        foreach (array(3, 4, 9) as $form) {
            $prefix = backport_mysqlnd_len(1, $form);
            for ($cut = 1; $cut < $form; $cut++) {
                $cases['rset-count-prefix-' . $form . '-' . $cut] = array('mode' => 'query', 'payload' => substr($prefix, 0, $cut));
            }
        }
        foreach (array('auth', 'change') as $mode) {
            $cases[$mode . '-unterminated-switch-name'] = array('mode' => $mode, 'payload' => "\xfemysql_native_password");
        }
        for ($cut = 0; $cut < 9; $cut++) {
            $prepare = "\0" . pack('V', 1) . pack('v2', 0, 0);
            $cases['prepare-cut-' . $cut] = array('mode' => 'prepare', 'payload' => substr($prepare, 0, $cut));
        }
        foreach (array(2, 3, 4) as $cut) {
            $cases['metadata-eof-cut-' . $cut] = array('mode' => 'metadata-eof', 'payload' => substr("\xfe\0\0\x02\0", 0, $cut));
            $cases['row-eof-cut-' . $cut] = array('mode' => 'row', 'payload' => substr("\xfe\0\0\x02\0", 0, $cut));
        }
    } elseif ($group === 'metadata') {
        $field = backport_mysqlnd_field();
        foreach (array(0, 1, 4, 7, strlen($field) - 13, strlen($field) - 1) as $cut) {
            $cases['field-cut-' . $cut] = array('mode' => 'metadata', 'payload' => substr($field, 0, $cut));
        }
        foreach (array(1, 3, 4, 9) as $form) {
            $cases['default-overrun-' . $form] = array('mode' => 'metadata', 'payload' => backport_mysqlnd_field(253, backport_mysqlnd_len(32, $form) . 'x'));
            $cases['default-control-' . $form] = array('mode' => 'metadata', 'payload' => backport_mysqlnd_field(253, backport_mysqlnd_len(1, $form) . 'x'), 'valid' => true, 'default' => 'x');
            $cases['name-control-' . $form] = array('mode' => 'metadata', 'payload' => backport_mysqlnd_field(253, '', $form), 'valid' => true, 'default' => '');
        }
        foreach (array(3, 4, 9) as $form) {
            $prefix = backport_mysqlnd_len(1, $form);
            for ($cut = 1; $cut < $form; $cut++) {
                $cases['default-prefix-' . $form . '-' . $cut] = array('mode' => 'metadata', 'payload' => $field . substr($prefix, 0, $cut));
                /* A leading FE packet shorter than eight bytes is the legacy
                 * COM_FIELD_LIST EOF form, rather than a field-length prefix. */
                if ($form !== 9 || $cut >= 8) {
                    $cases['name-prefix-' . $form . '-' . $cut] = array('mode' => 'metadata', 'payload' => substr($prefix, 0, $cut));
                }
            }
        }
        $cases['default-uint64-overflow'] = array('mode' => 'metadata', 'payload' => $field . "\xfe\0\0\0\0\1\0\0\0");
        $cases['default-uint32-null-alias'] = array('mode' => 'metadata', 'payload' => $field . "\xfe\xff\xff\xff\xff\0\0\0\0");
        $cases['default-empty'] = array('mode' => 'metadata', 'payload' => $field . "\0", 'valid' => true, 'default' => '');
        $cases['default-null'] = array('mode' => 'metadata', 'payload' => $field . "\xfb", 'valid' => true, 'default' => '');
        $cases['default-absent'] = array('mode' => 'metadata', 'payload' => $field, 'valid' => true, 'default' => '');
        /* The fixed metadata block carries a 12 (0x0c) length marker.  Any
         * other marker is a different packet shape and must be rejected. */
        $cases['marker-0b'] = array('mode' => 'metadata', 'payload' => backport_mysqlnd_field(253, '', 1, 64, "\x0b"), 'warning' => 'wrong fixed metadata length marker');
        $cases['marker-0d'] = array('mode' => 'metadata', 'payload' => backport_mysqlnd_field(253, '', 1, 64, "\x0d"), 'warning' => 'wrong fixed metadata length marker');
        $cases['marker-0c-control'] = array('mode' => 'metadata', 'payload' => backport_mysqlnd_field(253, '', 1, 64, "\x0c"), 'valid' => true, 'default' => '');
    } elseif ($group === 'rows') {
        foreach (array('row', 'binary') as $mode) {
            $head = $mode === 'binary' ? "\0\0" : '';
            foreach (array(3, 4, 9) as $form) {
                $prefix = backport_mysqlnd_len(1, $form);
                for ($cut = 1; $cut < $form; $cut++) {
                    /* FE with one or at least five bytes is a legacy EOF
                     * packet in this position. Do not call it a bad prefix. */
                    if ($mode === 'row' && $form === 9 && ($cut === 1 || ($cut >= 5 && $cut < 8))) continue;
                    $cases[$mode . '-prefix-' . $form . '-' . $cut] = array('mode' => $mode, 'payload' => $head . substr($prefix, 0, $cut));
                }
            }
            $cases[$mode . '-empty-packet'] = array('mode' => $mode, 'payload' => '');
            $cases[$mode . '-length-overrun'] = array('mode' => $mode, 'payload' => $head . "\x20x");
            $cases[$mode . '-length-uint64-overflow'] = array('mode' => $mode, 'payload' => $head . "\xfe\0\0\0\0\1\0\0\0");
            foreach (array(1, 3, 4, 9) as $form) {
                $cases[$mode . '-string-control-' . $form] = array('mode' => $mode, 'payload' => $head . backport_mysqlnd_len(3, $form) . 'abc', 'valid' => true, 'value' => 'abc');
            }
            $cases[$mode . '-empty-control'] = array('mode' => $mode, 'payload' => $head . "\0", 'valid' => true, 'value' => '');
            $cases[$mode . '-null-control'] = array('mode' => $mode, 'payload' => $mode === 'binary' ? "\0\4" : "\xfb", 'valid' => true, 'value' => null);
        }
        /* Bytes after the last column value belong to no column and are
         * rejected before any field codec runs. */
        $cases['row-trailing-junk'] = array('mode' => 'row', 'payload' => "\x01xJUNK", 'warning' => 'Invalid row data');
        $cases['row-null-trailing-junk'] = array('mode' => 'row', 'payload' => "\xfbJUNK", 'warning' => 'Invalid row data');
        $cases['binary-trailing-junk'] = array('mode' => 'binary', 'type' => 253, 'payload' => "\x00\x00\x01xJUNK", 'warning' => 'Invalid row data');
        $cases['binary-null-trailing-junk'] = array('mode' => 'binary', 'type' => 253, 'payload' => "\x00\x04JUNK", 'warning' => 'Invalid row data');
        $cases['binary-fixed-trailing-junk'] = array('mode' => 'binary', 'type' => 3, 'payload' => "\x00\x00" . pack('V', 42) . "JUNK", 'warning' => 'Invalid row data');
        foreach ($cases as $name => $case) {
            if ($case['mode'] === 'row') {
                $case['unbuffered'] = true;
                $cases[$name . '-unbuffered'] = $case;
            }
        }
        $cases['binary-missing-bitmap'] = array('mode' => 'binary', 'payload' => "\0");
        $cases['binary-invalid-null-prefix'] = array('mode' => 'binary', 'payload' => "\0\0\xfb");
        foreach (array(1 => 1, 2 => 2, 3 => 4, 4 => 4, 5 => 8, 8 => 8) as $type => $width) {
            for ($cut = 0; $cut < $width; $cut++) {
                $cases['binary-fixed-' . $type . '-' . $cut] = array('mode' => 'binary', 'type' => $type, 'payload' => "\0\0" . str_repeat("\0", $cut));
            }
        }
        foreach (array(10 => array(4), 11 => array(8, 12), 12 => array(4, 7, 11), 7 => array(4, 7, 11)) as $type => $widths) {
            foreach ($widths as $width) {
                $cases['binary-temporal-short-' . $type . '-' . $width] = array('mode' => 'binary', 'type' => $type, 'payload' => "\0\0" . chr($width) . str_repeat("\0", $width - 1));
            }
            $cases['binary-temporal-invalid-shape-' . $type] = array('mode' => 'binary', 'type' => $type, 'payload' => "\0\0\1x");
        }
        $cases['binary-int-control'] = array('mode' => 'binary', 'type' => 3, 'payload' => "\0\0" . pack('V', 42), 'valid' => true, 'value' => 42);
        $cases['binary-date-control'] = array('mode' => 'binary', 'type' => 10, 'payload' => "\0\0\4" . pack('v', 2026) . "\x0a\x16", 'valid' => true, 'value' => '2026-10-22');
        $cases['binary-time-control'] = array('mode' => 'binary', 'type' => 11, 'payload' => "\0\0\x08\0" . pack('V', 0) . "\x0c\x22\x38", 'valid' => true, 'value' => '12:34:56');
        $cases['binary-datetime-control'] = array('mode' => 'binary', 'type' => 12, 'payload' => "\0\0\x07" . pack('v', 2026) . "\x0a\x16\x0c\x22\x38", 'valid' => true, 'value' => '2026-10-22 12:34:56');
        $cases['binary-bitmap-control'] = array('mode' => 'binary', 'types' => array_fill(0, 8, 253), 'payload' => "\0\0\1" . str_repeat("\1x", 6) . "\0", 'valid' => true, 'values' => array('x', 'x', 'x', 'x', 'x', 'x', null, ''));
        /* Buffered prepared results take a separate decoder path
         * (mysqli_stmt_store_result), so every binary case runs twice. */
        foreach ($cases as $name => $case) {
            if ($case['mode'] === 'binary' && empty($case['store'])) {
                $case['store'] = true;
                $cases[$name . '-store'] = $case;
            }
        }
    } elseif ($group === 'auth-long') {
        foreach (array(0, 255, 256, 4096, 31622, 31626, 65536) as $length) {
            $cases['auth-switch-password-' . $length] = array('mode' => 'auth-long', 'length' => $length, 'valid' => true);
            $cases['change-user-password-' . $length] = array('mode' => 'change-long', 'length' => $length, 'valid' => true);
        }
    }
    return $cases;
}

function backport_mysqlnd_read_exact($socket, $length) {
    $data = '';
    while (strlen($data) < $length && !feof($socket)) {
        $part = fread($socket, $length - strlen($data));
        if ($part === false || $part === '') break;
        $data .= $part;
    }
    return strlen($data) === $length ? $data : false;
}

function backport_mysqlnd_read_packet($socket) {
    $header = backport_mysqlnd_read_exact($socket, 4);
    if ($header === false) return false;
    $length = ord($header[0]) | ord($header[1]) << 8 | ord($header[2]) << 16;
    if ($length > 131072) return false;
    return backport_mysqlnd_read_exact($socket, $length);
}

function backport_mysqlnd_send($socket, $body, $sequence) {
    $data = substr(pack('V', strlen($body)), 0, 3) . chr($sequence) . $body;
    $offset = 0;
    while ($offset < strlen($data)) {
        $written = @fwrite($socket, substr($data, $offset));
        if (!$written) return;
        $offset += $written;
    }
}

function backport_mysqlnd_peer($case) {
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$server) exit(2);
    $address = stream_socket_get_name($server, false);
    echo substr($address, strrpos($address, ':') + 1), "\n";
    flush();
    $socket = @stream_socket_accept($server, 5);
    fclose($server);
    if (!$socket) exit(3);
    stream_set_timeout($socket, 5);
    $mode = $case['mode'];
    $ok = "\0\0\0\x02\0\0\0";
    $eof = "\xfe\0\0\x02\0";
    if ($mode === 'greet') {
        backport_mysqlnd_send($socket, $case['payload'], 0);
    } else {
        backport_mysqlnd_send($socket, backport_mysqlnd_greeting(), 0);
        if (backport_mysqlnd_read_packet($socket) === false) exit(4);
        if ($mode === 'auth') {
            backport_mysqlnd_send($socket, $case['payload'], 2);
        } else {
            if ($mode !== 'auth-long') {
                backport_mysqlnd_send($socket, $ok, 2);
                if (backport_mysqlnd_read_packet($socket) === false) exit(5);
            }
            if ($mode === 'auth-long' || $mode === 'change-long') {
                $sequence = $mode === 'auth-long' ? 2 : 1;
                backport_mysqlnd_send($socket, "\xfemysql_clear_password\0", $sequence);
                $password = backport_mysqlnd_read_packet($socket);
                if ($password !== str_repeat('P', $case['length'])) exit(6);
                backport_mysqlnd_send($socket, $ok, $sequence + 2);
            } elseif ($mode === 'query' || $mode === 'ping' || $mode === 'change' || $mode === 'prepare') {
                backport_mysqlnd_send($socket, $case['payload'], 1);
            } else {
                $types = isset($case['types']) ? $case['types'] : array(isset($case['type']) ? $case['type'] : 253);
                $count = count($types);
                if ($mode === 'binary') {
                    backport_mysqlnd_send($socket, "\0" . pack('V', 1) . pack('v2', $count, 0) . "\0\0\0", 1);
                    foreach ($types as $i => $type) backport_mysqlnd_send($socket, backport_mysqlnd_field($type), $i + 2);
                    backport_mysqlnd_send($socket, $eof, $count + 2);
                    if (backport_mysqlnd_read_packet($socket) === false) exit(7);
                }
                backport_mysqlnd_send($socket, chr($count), 1);
                foreach ($types as $i => $type) {
                    backport_mysqlnd_send($socket, $mode === 'metadata' ? $case['payload'] : backport_mysqlnd_field($type), $i + 2);
                }
                backport_mysqlnd_send($socket, $mode === 'metadata-eof' ? $case['payload'] : $eof, $count + 2);
                backport_mysqlnd_send($socket, ($mode === 'row' || $mode === 'binary') ? $case['payload'] : "\1x", $count + 3);
                backport_mysqlnd_send($socket, $eof, $count + 4);
            }
        }
    }
    fclose($socket);
    exit(0);
}

function backport_mysqlnd_run($group) {
    $cases = backport_mysqlnd_cases($group);
    $failures = 0;
    $executed = 0;
    mysqli_report(MYSQLI_REPORT_OFF);
    foreach ($cases as $name => $case) {
        if (getenv('PHP_BACKPORT_MYSQLND_CASE') && getenv('PHP_BACKPORT_MYSQLND_CASE') !== $name) continue;
        $executed++;
        if (getenv('PHP_BACKPORT_MYSQLND_TRACE')) echo 'CASE ', $name, "\n";
        $php = PHP_BINARY;
        $command = escapeshellarg($php) . ' -n ' . escapeshellarg(__FILE__) . ' --peer ' . escapeshellarg($group) . ' ' . escapeshellarg($name);
        $process = proc_open($command, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, null, null, array('bypass_shell' => true));
        if (!is_resource($process)) die("Cannot start loopback peer\n");
        fclose($pipes[0]);
        stream_set_timeout($pipes[1], 10);
        $port = trim(fgets($pipes[1]));
        if ($port === '' || strspn($port, '0123456789') !== strlen($port)) die("Invalid loopback peer port\n");
        $warnings = array();
        set_error_handler(function ($number, $message) use (&$warnings) { $warnings[] = $message; return true; });
        $password = isset($case['length']) ? str_repeat('P', $case['length']) : 'P';
        if (getenv('PHP_BACKPORT_MYSQLND_DIRECT_CONNECT')) {
            /* Procedural connect allocates the handle non-persistent while
             * mysqli_init() always allocates it persistently; both cleanup
             * paths must release results exactly once. */
            $link = mysqli_connect('127.0.0.1', 'backport', $password, '', (int) $port);
            $result = (bool) $link;
        } else {
            $link = mysqli_init();
            mysqli_options($link, MYSQLI_OPT_CONNECT_TIMEOUT, 3);
            $result = mysqli_real_connect($link, '127.0.0.1', 'backport', $password, '', (int) $port);
        }
        if ($result && $case['mode'] !== 'auth' && $case['mode'] !== 'greet' && $case['mode'] !== 'auth-long') {
            if ($case['mode'] === 'change' || $case['mode'] === 'change-long') {
                $result = mysqli_change_user($link, 'backport', $password, '');
            } elseif ($case['mode'] === 'ping') {
                $result = mysqli_ping($link);
            } elseif ($case['mode'] === 'prepare' || $case['mode'] === 'binary') {
                $statement = mysqli_prepare($link, 'SELECT 1');
                $result = (bool) $statement;
                if ($result && $case['mode'] === 'binary') {
                    $result = mysqli_stmt_execute($statement);
                    if ($result && !empty($case['store'])) {
                        /* Buffered prepared result path. */
                        $result = mysqli_stmt_store_result($statement) === true;
                    }
                    if (isset($case['types'])) {
                        $values = array_fill(0, count($case['types']), null);
                        $arguments = array($statement);
                        foreach ($values as &$value) $arguments[] = &$value;
                        unset($value);
                        call_user_func_array('mysqli_stmt_bind_result', $arguments);
                    } else {
                        $value = null;
                        mysqli_stmt_bind_result($statement, $value);
                    }
                    $result = $result && mysqli_stmt_fetch($statement) === true;
                    if ($result && !empty($case['valid'])) {
                        $result = isset($case['values']) ? $values === $case['values'] : $value === $case['value'];
                    }
                }
                if ($statement) mysqli_stmt_close($statement);
            } else {
                $query = mysqli_query($link, 'SELECT 1', !empty($case['unbuffered']) ? MYSQLI_USE_RESULT : MYSQLI_STORE_RESULT);
                $result = (bool) $query;
                if ($query instanceof mysqli_result) {
                    if (array_key_exists('default', $case)) {
                        $field = mysqli_fetch_field($query);
                        $result = $field->def === $case['default'];
                    }
                    $row = mysqli_fetch_row($query);
                    $result = $result && is_array($row);
                    if ($result && array_key_exists('value', $case)) $result = $row[0] === $case['value'];
                    mysqli_free_result($query);
                }
            }
        }
        if ($link) mysqli_close($link);
        restore_error_handler();
        stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        $valid = !empty($case['valid']);
        $boundary_warning = false;
        /* Cases with a 'warning' expectation must see that exact rejection
         * message; a generic malformed warning from another phase is not
         * accepted as proof of this phase. */
        $phase_warning = empty($case['warning']);
        foreach ($warnings as $warning) {
            if (strpos($warning, 'Malformed') !== false || strpos($warning, 'Premature end of data') !== false || strpos($warning, 'Wrong COM_STMT_PREPARE response size') !== false) $boundary_warning = true;
            if (isset($case['warning']) && strpos($warning, $case['warning']) !== false) $phase_warning = true;
        }
        if ((bool) $result !== $valid || (!$valid && !$boundary_warning) || !$phase_warning || $status !== 0 || $stderr !== '') {
            echo 'FAIL ', $name, ': result=', (int) (bool) $result, ' peer=', $status, ' expected_warning=', isset($case['warning']) ? $case['warning'] : '-', ' warnings=', implode(' | ', $warnings), ' stderr=', $stderr, "\n";
            $failures++;
        }
    }
    /* executed/total: proves how many cases actually ran even when the
     * PHP_BACKPORT_MYSQLND_CASE debug filter is set. */
    echo $group, ': ', $executed, '/', count($cases), ' cases, ', $failures, " failures\n";
}

if (isset($argv[1]) && $argv[1] === '--peer') {
    $cases = backport_mysqlnd_cases($argv[2]);
    backport_mysqlnd_peer($cases[$argv[3]]);
}
