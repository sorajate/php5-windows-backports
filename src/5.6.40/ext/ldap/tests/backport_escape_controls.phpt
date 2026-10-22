--TEST--
LDAP escaping preserves binary strings, character lists, and filter/DN modes
--EXTENSIONS--
ldap
--FILE--
<?php
var_dump(ldap_escape("a\0b*()\\", '', LDAP_ESCAPE_FILTER) === 'a\\00b\\2a\\28\\29\\5c');
var_dump(ldap_escape('a,b+c', '', LDAP_ESCAPE_DN) === 'a\\2cb\\2bc');
var_dump(ldap_escape('a*b', '*', LDAP_ESCAPE_FILTER) === 'a*b');
var_dump(ldap_escape('') === '');
$data = str_repeat("*\0", 4096);
var_dump(strlen(ldap_escape($data, '', LDAP_ESCAPE_FILTER)) === 24576);
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
