--TEST--
Backport review: FILTER_VALIDATE_URL accepts two hexadecimal digits in percent-encoded userinfo
--EXTENSIONS--
filter
--FILE--
<?php
var_dump(filter_var("http://a%A1@example.com/", FILTER_VALIDATE_URL) !== false);
var_dump(filter_var("http://a%AF@example.com/", FILTER_VALIDATE_URL) !== false);
var_dump(filter_var("http://a%4F@example.com/", FILTER_VALIDATE_URL) !== false);
var_dump(filter_var("http://a%G1@example.com/", FILTER_VALIDATE_URL) === false);
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
