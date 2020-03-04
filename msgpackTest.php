<?php
date_default_timezone_set('UTC');
error_reporting(E_STRICT |  E_ALL);

require_once 'msgpack.php';

class DataTest extends PHPUnit_Framework_TestCase
{
    /**
     * test to make unpack(pack(val)) is identical.
     *
     * @dataProvider roundTripProvider
     */
    public function testRoundTrip($val)
    {
        $this->assertEquals($val, msgpack_unpackb(msgpack_packb($val)));
    }

    public function roundTripProvider()
    {
        return array(
            'zero: 0' => array(0),
            'small: 1' => array(1),
            'small: 5' => array(5),
            'small: -1' => array(-1),
            'small: -2' => array(-2),
            'small: 35' => array(35),
            'small: -35' => array(-35),
            'boundry: 127' => array(127),
            'boundry: -127' => array(-127),
            'boundry: 0x7F' => array(0x7f),
            'boundry: 0x80' => array(0x80),
            'boundry: -0x7F' => array(-0x7f),
            'boundry: -0x80' => array(-0x80),
            'boundry: 0xFF' => array(0xff),
            'boundry: 0x7FFF' => array(0x7FFF),
            'boundry: -0x7FFF' => array(-0x7FFF),
            'boundry: 0x8000' => array(0x8000),
            'boundry: -0x8000' => array(-0x8000),
            'boundry: 0xFFFF' => array(0xFFFF),
            'boundry: -0xFFFF' => array(-0xFFFF),
            'boundry: 0x7FFFFFFF' => array(0x7fFFFFFF),
            'boundry: 0x80000000' => array(0x80000000),
            'boundry: 0xFFFFFFFF' => array(0xFFFFFFFF),
            'small: 128' => array(128),
            'small: -128' => array(-128),
            'medium: 1000' => array(1000),
            'medium: -1000' => array(-1000),
            'large: 100000' => array(100000),
            'large: -100000' => array(-100000),
            'huge: 10000000000' => array(10000000000),
            'huge: -10000000000' => array(-10000000000),
            'gigant: -223372036854775807' => array(-223372036854775807),
            'gigant: -9223372036854775807' => array(-9223372036854775807),
            'null' => array(null),
            'true' => array(true),
            'false' => array(false),
            'double: 0.1' => array(0.1),
            'double: 1.1' => array(1.1),
            'double: 123.456' => array(123.456),
            'double: -123456789.123456789' => array(-123456789.123456789),
            'double: 1e128' => array(1e128),
            'empty: ""' => array(""),
            'string: "foobar"' => array("foobar"),
            'string: "Lorem ipsum dolor sit amet amet."' => array("Lorem ipsum dolor sit amet amet."),
            'string: ""' => array(""),
            'array("foo", "foo", "foo")' => array(array("foo", "foo", "foo")),
            'array("one" => 1, "two" =>  2)' => array(array("one" =>  1, "two" =>  2)),
            'array("kek" =>  "lol", "lol" => "kek")' => array(array("kek" => "lol", "lol" => "kek")),
            'array("")' => array( array() ),
            'array(1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16)' => array(array(1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16)),
            'associative array with more than 15 entries' => array(array("f1"=>1,"f2"=>2,"f3"=>3,"f4"=>4,"f5"=>5,"f6"=>6,"f7"=>7,"f8"=>8,"f9"=>9,"f10"=>10,"f11"=>11,"f12"=>12,"f13"=>13,"f14"=>14,"f15"=>15,"f16"=>16)),
        );
    }

    // PHP has poor binary support for 16-bit integers, so just iterate of all of them
    public function testShortIntTrip()
    {
        for ($i = -0x10000; $i <= 0x10000; $i += 29) {
            $this->assertEquals($i, msgpack_unpackb(msgpack_packb($i)));
        }
    }

    /**
     * test to make unpack(pack(val)) is identical
     *
     * @dataProvider negativesProvider
     */
    public function testNegatives($hex, $val)
    {
        $this->assertEquals($val, msgpack_unpackb(hex2bin($hex)));
    }

    public function negativesProvider()
    {
        return array(
            // 8-bit signed integers
            array("d000", 0),
            array("d001", 1),
            array("d0ff", -1),
            array("d0fe", -2),

            // 16-bit signed integers
            // –32,768 to 32,767
            array("d10000", 0),
            array("d10001", 1),
            array("d17fff", 0x7FFF),
            array("d18000", -0x8000),
            array("d1fffe", -2),
            array("d1ffff", -1),

            // 32-bit signed integers
            array("d200000000", 0),
            array("d200000001", 1),
            array("d27fffFFFF", 0x7FFFFFFF),
            array("d280000000", -0x80000000),
            array("d2ffFFFFfe", -2),
            array("d2ffffFFFF", -1),

            // 64-bit signed integers
            array("d30000000000000000", 0),
            array("d30000000000000001", 1),
            array("d3ffffffffffffffff", -1),
            array("d30000000100000000", 0x100000000),
            array("d300000001ffffffff", 0x1ffffffff),
            array("d30fffffffffffffff", 0x0fffffffffffffff),
            array("d31fffffffffffffff", 0x1fffffffffffffff)
        );
    }
}
?>
