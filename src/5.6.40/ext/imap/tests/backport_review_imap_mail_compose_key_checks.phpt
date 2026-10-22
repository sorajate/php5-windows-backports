--TEST--
Backport review: imap_mail_compose checks MIME parameter and disposition keys and values
--SKIPIF--
<?php
if (!extension_loaded("imap")) die("skip imap extension not available");
?>
--FILE--
<?php
function compose_with_parameter($field, $key, $value) {
	$part = array(
		"type" => TYPETEXT,
		"subtype" => "plain",
		"contents.data" => "body"
	);
	$part[$field] = array($key => $value);
	return imap_mail_compose(array(), array($part));
}

var_dump(is_string(compose_with_parameter("type.parameters", "name", "safe")));
var_dump(is_string(compose_with_parameter("type.parameters", 0, "safe")));
var_dump(compose_with_parameter("type.parameters", "bad\nname", "safe"));
var_dump(compose_with_parameter("type.parameters", "name", "bad\nvalue"));
var_dump(is_string(compose_with_parameter("disposition", "filename", "safe")));
var_dump(compose_with_parameter("disposition", "bad\nname", "safe"));
var_dump(compose_with_parameter("disposition", "filename", "bad\nvalue"));
?>
--EXPECTF--
bool(true)
bool(true)

Warning: imap_mail_compose(): header injection attempt in body type.parameters key in %s on line %d
bool(false)

Warning: imap_mail_compose(): header injection attempt in body type.parameters value in %s on line %d
bool(false)
bool(true)

Warning: imap_mail_compose(): header injection attempt in body disposition key in %s on line %d
bool(false)

Warning: imap_mail_compose(): header injection attempt in body disposition value in %s on line %d
bool(false)
