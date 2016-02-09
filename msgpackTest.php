<?php


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
        $this->assertEquals($val, msgpack_unpack(msgpack_pack($val)));
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
        );
    }

    // PHP has poor binary support for 16-bit integers, so just iterate of all of them
    public function testShortIntTrip()
    {
        for ($i = -0xFFFF; $i <= 0xFFFF; $i++) {
            $this->assertEquals($i, msgpack_unpack(msgpack_pack($i)));
        }
    }

    /**
     * test to make unpack(pack(val)) is identical
     *
     * @dataProvider negativesProvider
     */
    public function testNegatives($hex, $val)
    {
        $this->assertEquals($val, msgpack_unpack(hex2bin($hex)));
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