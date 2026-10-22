<?php
/* Small, in-memory USTAR fixtures for the Phar security regression tests. */
function phar_security_tar_header($name, $size, $type = '0', $link = '', $fields = array())
{
    $header = pack('a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12',
        $name,
        isset($fields['mode']) ? $fields['mode'] : "0000644\0",
        "0000000\0", "0000000\0", $size,
        isset($fields['mtime']) ? $fields['mtime'] : "00000000000\0",
        '        ', $type, $link, 'ustar', '00', '', '', '', '', '', '');
    $sum = array_sum(unpack('C*', $header));
    $checksum = isset($fields['checksum']) ? $fields['checksum'] : sprintf("%06o\0 ", $sum);
    return substr_replace($header, str_pad($checksum, 8, "\0"), 148, 8);
}

function phar_security_tar_entry($name, $contents, $type = '0', $link = '', $fields = array())
{
    return phar_security_tar_header($name, decoct(strlen($contents)), $type, $link, $fields)
        . $contents . str_repeat("\0", (512 - strlen($contents) % 512) % 512);
}
?>
