<?php
$detail = array();
foreach ($_FILES as $name => $info) {
    $data = @file_get_contents($info['tmp_name']);
    $detail[] = $name . '/' . $info['name'] . '/' . $info['size'] . '/' . $info['error'] . '/' . ($data === false ? '-' : $data);
}
echo "RESULT post=", count($_POST), " files=", count($_FILES), " detail=", $detail ? implode(',', $detail) : '-', "\n";
