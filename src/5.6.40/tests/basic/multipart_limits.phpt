--TEST--
multipart body-part limits, ignored parts and file-upload warning collapsing (CGI)
--SKIPIF--
<?php
if (!getenv('TEST_PHP_CGI_EXECUTABLE')) {
    die("skip php-cgi not available");
}
?>
--FILE--
<?php

$script = __DIR__ . '/multipart_limits.inc';

function run_case($name, $script, $ini, $parts) {
    $boundary = '----MultipartLimits7x';
    $body = '';
    foreach ($parts as $part) {
        $body .= "--$boundary\r\n";
        if (isset($part['disposition'])) {
            $body .= 'Content-Disposition: ' . $part['disposition'] . "\r\n";
        }
        if (isset($part['type'])) {
            $body .= 'Content-Type: ' . $part['type'] . "\r\n";
        }
        $body .= "\r\n" . $part['body'] . "\r\n";
    }
    $body .= "--$boundary--\r\n";

    /* Windows php-cgi rejects a script passed as an argument or via -f,
     * so the target script is taken from the SCRIPT_FILENAME environment
     * variable below. */
    $cmd = escapeshellarg(getenv('TEST_PHP_CGI_EXECUTABLE'))
        . ' -C -n -ddisplay_errors=1 -derror_reporting=-1 -dlog_errors=1 '
        . $ini;

    /* Multipart warnings are raised before the output layer is ready, so
     * they arrive through the error log (stderr under CGI) and are counted in
     * the combined capture below.
     * proc_open() with an environment array is unreliable on Windows, so the
     * CGI variables go into this process environment and the child inherits
     * it (the environment parameter is omitted on purpose). */
    putenv('REDIRECT_STATUS=1');
    putenv('CONTENT_TYPE=multipart/form-data; boundary=' . $boundary);
    putenv('CONTENT_LENGTH=' . strlen($body));
    putenv('REQUEST_METHOD=POST');
    putenv('SCRIPT_FILENAME=' . $script);

    $pipes = array();
    $handle = proc_open($cmd, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, getcwd());
    fwrite($pipes[0], $body);
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($handle);

    $post = $files = '?';
    if (preg_match('/RESULT post=(\d+) files=(\d+)/', $out, $m)) {
        $post = $m[1];
        $files = $m[2];
    }
    $limit = substr_count($out, 'Multipart body parts limit exceeded');
    $limit_value = '-';
    if (preg_match('/Multipart body parts limit exceeded (\d+)/', $out, $m)) {
        $limit_value = $m[1];
    }
    $upload = substr_count($out, 'Maximum number of allowable file uploads has been exceeded');
    $garbled = substr_count($out, 'File Upload Mime headers garbled');
    echo "$name: post=$post files=$files limit_warn=$limit limit_value=$limit_value upload_warn=$upload garbled=$garbled\n";
}

$named = function ($i) {
    return array('disposition' => 'form-data; name="f' . $i . '"', 'body' => 'v' . $i);
};
$file = function ($i) {
    return array('disposition' => 'form-data; name="u' . $i . '"; filename="t' . $i . '.txt"',
                 'type' => 'text/plain', 'body' => 'data' . $i);
};

/* 1. Custom limit: three named parts against a cap of two. */
run_case('custom_limit', $script, '-dmax_multipart_body_parts=2',
    array($named(0), $named(1), $named(2)));

/* 2. Derived default: -1 resolves to max_input_vars + max_file_uploads = 3 + 0. */
run_case('derived_default', $script,
    '-dmax_multipart_body_parts=-1 -dmax_input_vars=3 -dmax_file_uploads=0',
    array($named(0), $named(1), $named(2), $named(3)));

/* 3. A part without Content-Disposition is ignored but still consumes a
 *    body-part slot: with a cap of two the third part hits the limit before
 *    the missing-disposition check, so no garbled warning is reached. */
run_case('ignored_part_counted', $script, '-dmax_multipart_body_parts=2',
    array($named(0), $named(1), array('body' => 'ignored')));

/* 4. File-upload limit warning collapses to one message for three excess files. */
run_case('file_limit_warn_once', $script, '-dmax_file_uploads=1 -dmax_multipart_body_parts=10',
    array($file(0), $file(1), $file(2)));

/* 5. Ordinary fields and files stay unaffected. */
run_case('normal_fields_files', $script, '-dmax_multipart_body_parts=10',
    array($named(0), $file(1)));

?>
--EXPECT--
custom_limit: post=2 files=0 limit_warn=1 limit_value=2 upload_warn=0 garbled=0
derived_default: post=3 files=0 limit_warn=1 limit_value=3 upload_warn=0 garbled=0
ignored_part_counted: post=2 files=0 limit_warn=1 limit_value=2 upload_warn=0 garbled=0
file_limit_warn_once: post=0 files=1 limit_warn=0 limit_value=- upload_warn=1 garbled=0
normal_fields_files: post=1 files=1 limit_warn=0 limit_value=- upload_warn=0 garbled=0
