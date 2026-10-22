--TEST--
Backport review: exif_scan_thumbnail bounds the JPEG length field and parses a valid control
--SKIPIF--
<?php if (!extension_loaded('exif')) die('skip exif extension not available'); ?>
--FILE--
<?php
function make_jpeg_with_thumbnail($thumbnail) {
	$tiff = "II" . pack("v", 42) . pack("V", 8);
	$tiff .= pack("v", 0) . pack("V", 14);
	$tiff .= pack("v", 2);
	$tiff .= pack("v", 0x0201) . pack("v", 4) . pack("V", 1) . pack("V", 44);
	$tiff .= pack("v", 0x0202) . pack("v", 4) . pack("V", 1) . pack("V", strlen($thumbnail));
	$tiff .= pack("V", 0) . $thumbnail;
	$payload = "Exif\0\0" . $tiff;
	/* A minimal SOS ends the marker scan the way ordinary JPEGs do; a
	 * stream that stops at EOI is treated as a tables-only image. */
	return "\xFF\xD8\xFF\xE1" . pack("n", strlen($payload) + 2) . $payload
		. "\xFF\xDA" . pack("n", 8) . "\x01\x01\x00\x00\x3f\x00" . "\xFF\xD9";
}

$short_thumbnail = "\xFF\xD8\xFF\xD9\x00";
/* SOF0 stores height (3) before width (2) */
$valid_thumbnail = "\xFF\xD8\xFF\xC0" . pack("n", 11) . "\x08" . pack("n", 3) . pack("n", 2) . "\x01\x01\x11\x00\xFF\xD9";
$short_file = tempnam(sys_get_temp_dir(), "exif");
$valid_file = tempnam(sys_get_temp_dir(), "exif");
file_put_contents($short_file, make_jpeg_with_thumbnail($short_thumbnail));
file_put_contents($valid_file, make_jpeg_with_thumbnail($valid_thumbnail));

$width = $height = 99;
$thumbnail = exif_thumbnail($short_file, $width, $height);
var_dump(strlen($thumbnail), $width, $height);

$width = $height = 99;
$thumbnail = exif_thumbnail($valid_file, $width, $height);
var_dump(strlen($thumbnail), $width, $height);

unlink($short_file);
unlink($valid_file);
?>
--EXPECT--
int(5)
int(0)
int(0)
int(17)
int(2)
int(3)
