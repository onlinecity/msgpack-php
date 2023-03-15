<?php

/**
 * Determine if string is valid UTF-8 encoding. This does not mean
 * that it is necessarily a UTF-8 string, but is used to decide on the
 * default type if not explicitly set otherwise. It is not
 * constant time.
 *
 * A string is valid UTF-8 if all of the following are true:
 * 1. Single byte characters are 0xxxxxxx (00-7F)
 * 2. Two byte characters are 110xxxxx 10xxxxxx ((C,D)X (8,9,A,B)Y)
 * 3. Three byte characters are 1110xxxx 10xxxxxx 10xxxxxx (EX (8,9,A,B)Y (8,9,A,B)Z)
 * 4. Four byte characters are 11110xxx 10xxxxxx 10xxxxxx 10xxxxxx (FX (8,9,A,B)Y (8,9,A,B)Z (8,9,A,B)W)
 * 5. Does not contain disallowed bytes (hex):
 *    C0, C1, F5, F6, F7, F8, F9, FA, FB, FC, FD, FE, FF
 * 6. If first byte is E0 or F0, second byte must be >=A0
 * 7. If first byte is F4, second byte must be <84
 * 8. If first byte is ED, second byte must be <A0
 *
 * @param string $input
 * @return boolean
 */
function is_disallowed_utf8($inp) 
{
    if ($inp == 0xC0 || $inp == 0xC1 || $inp >= 0xF5) return true;
    return false;
}
function is_utf8($input)
{
    $pos = 0;
    while ($pos < strlen($input)) {
      $byte = ord(substr($input,$pos++,1));
      if (is_disallowed_utf8($byte)) return false; // not allowed character
      if ($byte >= 0xC0 && $byte <= 0xDF) {
        // two-byte sequence
        if ($pos+1 > strlen($input)) return false; // too short
        $byte2 = ord(substr($input,$pos++,1));
        if ($byte2 < 0x80 || $byte2 > 0xBF || is_disallowed_utf8($byte2)) return false; // not continuation byte
      } else if ($byte >= 0xE0 && $byte <= 0xEF) {
        // three-byte sequence
        if ($pos+2 > strlen($input)) return false; // too short
        $byte2 = ord(substr($input,$pos++,1));
        $byte3 = ord(substr($input,$pos++,1));
        if ($byte == 0xE0 && $byte2 < 0xA0) return false; // overlong (should be only two bytes)
        if ($byte == 0xED && $byte2 >= 0xA0) return false; // surrogate halves reserved for UTF-16
        if ($byte2 < 0x80 || $byte2 > 0xBF || is_disallowed_utf8($byte2)) return false; // not continuation byte
        if ($byte3 < 0x80 || $byte3 > 0xBF || is_disallowed_utf8($byte3)) return false; // not continuation byte
      } else if ($byte >= 0xF0 && $byte <0xFF) {
        // four-byte sequence
        if ($pos+3 > strlen($input)) return false; // too short
        $byte2 = ord(substr($input,$pos++,1));
        $byte3 = ord(substr($input,$pos++,1));
        $byte4 = ord(substr($input,$pos++,1));
        if ($byte == 0xF0 && $byte2 < 0xA0) return false; // overlong (should be only three bytes)
        if ($byte2 < 0x80 || $byte2 > 0xBF || is_disallowed_utf8($byte2)) return false; // not continuation byte
        if ($byte3 < 0x80 || $byte3 > 0xBF || is_disallowed_utf8($byte3)) return false; // not continuation byte
        if ($byte4 < 0x80 || $byte4 > 0xBF || is_disallowed_utf8($byte4)) return false; // not continuation byte
      }
      // otherwise, 0x00-0x7F, valid UTF-8 bytes 
    }
    return true;
}


/**
 * Pack some input into msgpack format.
 * Format specs: https://github.com/msgpack/msgpack/blob/master/spec.md
 *
 * @param mixed $input
 * @param boolean $use_bin_type=False
 * @param boolean $force_str_as_bin=False
 * @return string
 * @throws \InvalidArgumentException
 */
function msgpack_packb($input, $use_bin_type=False, $force_str_as_bin=False)
{
    static $bigendian;
    if (!isset($bigendian)) $bigendian = (pack('S',1)==pack('n',1));

    // null
    if (is_null($input)) {
        return pack('C',0xC0);
    }

    // booleans
    if (is_bool($input)) {
        return pack('C',$input ? 0xC3 : 0xC2);
    }

    // Integers
    if (is_int($input)) {
        // positive fixnum
        if (($input|0x7F) == 0x7F) return pack('C',$input&0x7F);
        // negative fixnum
        if ($input < 0 && $input>=-32) return pack('c',$input);
        // uint8
        if ($input > 0 && $input <= 0xFF) return pack('CC',0xCC,$input);
        // uint16
        if ($input > 0 && $input <= 0xFFFF) return pack('Cn',0xCD,$input);
        // uint32
        if ($input > 0 && $input <= 0xFFFFFFFF) return pack('CN',0xCE,$input);
        // uint64
        if ($input > 0 && $input <= 0xFFFFFFFFFFFFFFFF) {
            // pack() does not support 64-bit ints, so pack into two 32-bits
            $h = ($input&0xFFFFFFFF00000000)>>32;
            $l = $input&0xFFFFFFFF;
            return $bigendian ? pack('CNN',0xCF,$l,$h) : pack('CNN',0xCF,$h,$l);
        }
        // int8
        if ($input < 0 && $input >= -0x80) return pack('Cc',0xD0,$input);
        // int16
        if ($input < 0 && $input >= -0x8000) {
            $p = pack('s',$input);
            return pack('Ca2',0xD1,$bigendian ? $p : strrev($p));
        }
        // int32
        if ($input < 0 && $input >= -0x80000000) {
            $p = pack('l',$input);
            return pack('Ca4',0xD2,$bigendian ? $p : strrev($p));
        }
        // int64
        if ($input < 0 && $input >= -0x8000000000000000) {
            // pack() does not support 64-bit ints either so pack into two 32-bits
            $p1 = pack('l',$input&0xFFFFFFFF);
            $p2 = pack('l',($input>>32)&0xFFFFFFFF);
            return $bigendian ? pack('Ca4a4',0xD3,$p1,$p2) : pack('Ca4a4',0xD3,strrev($p2),strrev($p1));
        }
        throw new \InvalidArgumentException('Invalid integer: '.$input);
    }

    // Floats
    if (is_float($input)) {
        // Just pack into a double, don't take any chances with single precision
        return pack('C',0xCB).($bigendian ? pack('d',$input) : strrev(pack('d',$input)));
    }

    // Strings / Binary
    if (is_string($input) && (!$use_bin_type || (!$force_str_as_bin && is_utf8($input)))) {
        $len = strlen($input);
        if ($len<32) { //fixstr
            return pack('Ca*',0xA0|$len,$input);
        } else if ($len<=0xFF && $use_bin_type) { //str8 only if bin types are available
            return pack('CCa*',0xD9,$len,$input);
        } else if ($len<=0xFFFF) { //str16
            return pack('Cna*',0xDA,$len,$input);
        } else if ($len<=0xFFFFFFFF) { //str32
            return pack('CNa*',0xDB,$len,$input);
        } else {
            throw new \InvalidArgumentException('Input overflows (2^32)-1 byte max');
        }
    }
    if (is_string($input) && ($use_bin_type && ($force_str_as_bin || !is_utf8($input)))) {
        $len = strlen($input);
        if ($len<=0xFF) { //bin8
            return pack('CCa*',0xC4,$len,$input);
        } else if ($len<=0xFFFF) { //bin16
            return pack('Cna*',0xC5,$len,$input);
        } else if ($len<=0xFFFFFFFF) { //bin32
            return pack('CNa*',0xC6,$len,$input);
        } else {
            throw new \InvalidArgumentException('Input overflows (2^32)-1 byte max');
        }
    }


    // Arrays & Maps
    if (is_array($input)) {
        $keys = array_keys($input);
        $len = count($input);

        // Is this an associative array?
        $isMap = false;
        foreach ($keys as $key) {
            if (!is_int($key)) {
                $isMap = true;
                break;
            }
        }

        $buf = '';
        if ($len<16) {
            $buf .= pack('C',($isMap?0x80:0x90)|$len);
        } else if ($len<=0xFFFF) {
            $buf .= pack('Cn',($isMap?0xDE:0xDC),$len);
        } else if ($len<=0xFFFFFFFF) {
            $buf .= pack('CN',($isMap?0xDF:0xDD),$len);
        } else {
            throw new \InvalidArgumentException('Input overflows (2^32)-1 max elements');
        }

        foreach ($input as $key => $elm) {
            if ($isMap) $buf .= msgpack_packb($key, $use_bin_type, $force_str_as_bin);
            $buf .= msgpack_packb($elm, $use_bin_type, $force_str_as_bin);
        }
        return $buf;

    }

    throw new \InvalidArgumentException('Not able to pack/serialize input type: '.gettype($input));
}

/**
 * Unpack data from a msgpack'ed string
 *
 * @param string $input
 * @param boolean $raw=True
 * @return mixed
 */
function msgpack_unpackb($input, $raw=True)
{
    static $bigendian;
    if (!isset($bigendian)) $bigendian = (pack('S',1)==pack('n',1));

    // Use static variables so we can more easily handle recursive decoding
    static $buffer;
    static $pos;
    if (!isset($buffer) || ($buffer!=$input) || $pos==strlen($buffer)) {
        $buffer = $input;
        $pos = 0;
    }

    // Read a single byte
    $byte = substr($buffer,$pos++,1);


    // null
    if ($byte == "\xC0") return null;

    // booleans
    if ($byte == "\xC2") return false;
    if ($byte == "\xC3") return true;

    // positive fixnum
    if (($byte & "\x80") == "\x00") {
        return current(unpack('C',$byte&"\x7F"));
    }

    // negative fixnum
    if (($byte & "\xE0") == "\xE0") {
        return current(unpack('c',$byte&"\xFF"));
    }

    // fixstr
    if ((($byte ^ "\xA0") & "\xE0") == "\x00") {
        $len = current(unpack('c',($byte ^ "\xA0")));
        if ($len == 0) return "";
        $d = substr($buffer,$pos,$len);
        $pos+=$len;
        $toret = current(unpack('a'.$len,$d));
        if ($raw || is_utf8($toret)) return $toret;
        throw new \InvalidArgumentException('Can\'t unpack fixstr data that is not valid utf8: '.$toret);
    }

    // fixarray, array16/32
    if ((($byte ^ "\x90") & "\xF0") == "\x00") {
        // fixed array
        $len = current(unpack('c',($byte ^ "\x90")));
        $data = array();
        for($i=0;$i<$len;$i++) {
            $data[] = msgpack_unpackb($input, $raw);
        }
        return $data;
    } else if ($byte == "\xDC" || $byte == "\xDD") {
        if ($byte == "\xDC") {
            $d = substr($buffer,$pos,2);
            $pos+=2;
            $len = current(unpack('n',$d));
        }
        if ($byte == "\xDD") {
            $d = substr($buffer,$pos,4);
            $pos+=4;
            $len = current(unpack('N',$d));
        }
        $data = array();
        for($i=0;$i<$len;$i++) {
            $data[] = msgpack_unpackb($input, $raw);
        }
        return $data;
    }

    // fixmap, map16/32
    if ((($byte ^ "\x80") & "\xF0") == "\x00") {
        // fixed map
        $len = current(unpack('c',($byte ^ "\x80")));
        $data = array();
        for($i=0;$i<$len;$i++) {
            $key = msgpack_unpackb($input, $raw);
            $value = msgpack_unpackb($input, $raw);
            $data[$key] = $value;
        }
        return $data;
    } else if ($byte == "\xDE" || $byte == "\xDF") {
        if ($byte == "\xDE") {
            $d = substr($buffer,$pos,2);
            $pos+=2;
            $len = current(unpack('n',$d));
        }
        if ($byte == "\xDF") {
            $d = substr($buffer,$pos,4);
            $pos+=4;
            $len = current(unpack('N',$d));
        }
        $data = array();
        for($i=0;$i<$len;$i++) {
            $key = msgpack_unpackb($input, $raw);
            $value = msgpack_unpackb($input, $raw);
            $data[$key] = $value;
        }
        return $data;
    }

    switch ($byte) {
        // Unsigned integers
    case "\xCC": // uint 8
        return current(unpack('C',substr($buffer,$pos++,1)));
    case "\xCD": // uint 16
        $d = substr($buffer,$pos,2);
        $pos+=2;
        return current(unpack('n',$d));
    case "\xCE": // uint 32
        $d = substr($buffer,$pos,4);
        $pos+=4;
        return current(unpack('N',$d));
    case "\xCF": // uint 64
        $d = substr($buffer,$pos,8);
        $pos+=8;
        // Unpack into two uint32 and re-assemble
        $dat = unpack('Np1/Np2',$d);
        $dat['p1'] = $dat['p1'] << 32;
        return $dat['p1']|$dat['p2'];

        // Signed integers
    case "\xD0": // int 8
        return current(unpack('c',substr($buffer,$pos++,1)));
    case "\xD1": // int 16
        $d = substr($buffer,$pos,2);
        $pos+=2;
        // PHP does not have a "signed short, big-endian" unpacker
        // Get unsigned version and convert to negative if needed
        $unsigned = current(unpack('n',$d));
        return ($unsigned < 0x8000) ? $unsigned : ($unsigned & 0x7FFF) - 0x8000;
    case "\xD2": // int 32
        $d = substr($buffer,$pos,4);
        $pos+=4;
        // again, there is no "int32, big-endian" unpacker
        // the following might work on 32-bit machines, but fails on 64-bit
        //return (current(unpack('N',~$d))+1)*-1;
        $unsigned = current(unpack('N', $d));
        return ($unsigned < 0x80000000) ? $unsigned : ($unsigned & 0x7FFFFFFF) - 0x80000000;
    case "\xD3": // int 64
        $d = substr($buffer,$pos,8);
        $pos+=8;
        $dat = unpack('Np1/Np2',~$d);
        // this next line will cause p1 to be negative if
        //   high bit is set, on 64-bit machines
        $dat['p1'] = $dat['p1'] << 32;
        return (($dat['p1']|$dat['p2'])+1)*-1;

        // str8/16/32
    case "\xD9": // str8
        $d = substr($buffer,$pos,1);
        $pos+=1;
        $len = current(unpack('C',$d));
        $d = substr($buffer,$pos,$len);
        $pos+=$len;
        $toret = current(unpack('a'.$len,$d));
        if ($raw || is_utf8($toret)) return $toret;
        throw new \InvalidArgumentException('Can\'t unpack str8 data that is not valid utf8: '.$toret);
    case "\xDA": // str16
        $d = substr($buffer,$pos,2);
        $pos+=2;
        $len = current(unpack('n',$d));
        $d = substr($buffer,$pos,$len);
        $pos+=$len;
        $toret = current(unpack('a'.$len,$d));
        if ($raw || is_utf8($toret)) return $toret;
        throw new \InvalidArgumentException('Can\'t unpack str16 data that is not valid utf8: '.$toret);
    case "\xDB": // str32
        $d = substr($buffer,$pos,4);
        $pos+=4;
        $len = current(unpack('N',$d));
        $d = substr($buffer,$pos,$len);
        $pos+=$len;
        $toret = current(unpack('a'.$len,$d));
        if ($raw || is_utf8($toret)) return $toret;
        throw new \InvalidArgumentException('Can\'t unpack str32 data that is not valid utf8: '.$toret);

        // bin8/16/32
    case "\xC4": // bin8
        $d = substr($buffer,$pos,1);
        $pos+=1;
        $len = current(unpack('C',$d));
        $d = substr($buffer,$pos,$len);
        $pos+=$len;
        return current(unpack('a'.$len,$d));
    case "\xC5": // bin16
        $d = substr($buffer,$pos,2);
        $pos+=2;
        $len = current(unpack('n',$d));
        $d = substr($buffer,$pos,$len);
        $pos+=$len;
        return current(unpack('a'.$len,$d));
    case "\xC6": // bin32
        $d = substr($buffer,$pos,4);
        $pos+=4;
        $len = current(unpack('N',$d));
        $d = substr($buffer,$pos,$len);
        $pos+=$len;
        return current(unpack('a'.$len,$d));

        // Floats
    case "\xCA": // single-precision
        $d = substr($buffer,$pos,4);
        $pos+=4;
        return current(unpack('f',$bigendian ? $d : strrev($d)));
    case "\xCB": // double-precision
        $d = substr($buffer,$pos,8);
        $pos+=8;
        return current(unpack('d',$bigendian ? $d : strrev($d)));

    }

    // Not handled: ext8/16/32, fixext1/2/4/8/16, (never used byte)
    throw new \InvalidArgumentException('Can\'t unpack data with byte-header: '.$byte);
}
