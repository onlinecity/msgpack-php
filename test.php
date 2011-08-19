<?php
/*
 * Selected tests from msgpack test suite:
 * https://github.com/msgpack/msgpack/tree/master/php/tests
 */

require_once 'msgpack.php';

function test($type, $var)
{
	echo "================\n".$type."\n";
	$e = msgpack_pack($var);
	$d = msgpack_unpack($e);

	echo "\t".bin2hex($e)."\t".$e."\n\t";
	echo str_replace("\n","\n\t",var_export($d,true))."\n";
	if ($var === $d) {
		echo "PASS\n";
	} else {
		echo "FAIL\n";
	}
}


test('zero: 0', 0);
test('small: 1', 1);
test('small: 5', 5);
test('small: -1', -1);
test('small: -2', -2);
test('small: 35', 35);
test('small: -35', -35);
test('small: 128', 128);
test('small: -128', -128);
test('medium: 1000', 1000);
test('medium: -1000', -1000);
test('large: 100000', 100000);
test('large: -100000', -100000);
test('huge: 10000000000', 10000000000);
test('huge: -10000000000', -10000000000);
test('gigant: -223372036854775807', -223372036854775807);
test('gigant: -9223372036854775807', -9223372036854775807);
test('null',null);
test('true',true);
test('false',false);
test('double: 0.1', 0.1);
test('double: 1.1', 1.1);
test('double: 123.456', 123.456);

test('empty: ""', "");
test('string: "foobar"', "foobar");
test('string: "foobar"', "Lorem ipsum dolor sit amet amet.");

test('array("foo", "foo", "foo")', array("foo", "foo", "foo"));
test('array("one" => 1, "two" => 2))', array("one" => 1, "two" => 2));
test('array("kek" => "lol", "lol" => "kek")', array("kek" => "lol", "lol" => "kek"));
test('array("" => "empty")', array("" => "empty"));