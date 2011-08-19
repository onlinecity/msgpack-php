<?php

/**
 * Pack some input into msgpack format.
 * Format specs: http://wiki.msgpack.org/display/MSGPACK/Format+specification
 * 
 * @param mixed $input
 * @return string
 * @throws \InvalidArgumentException
 */
function msgpack_pack($input)
{
	$bigendian = (pack('S',1)==pack('n',1));

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

	// Strings/Raw
	if (is_string($input)) {
		$len = strlen($input);
		if ($len<32) {
			return pack('Ca*',0xA0|$len,$input);
		} else if ($len<=0xFFFF) {
			return pack('Cna*',0xDA,$len,$input);
		} else if ($len<=0xFFFFFFFF) {
			return pack('CNa*',0xDB,$len,$input);
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
			if ($isMap) $buf .= msgpack_pack($key);
			$buf .= msgpack_pack($elm);
		}
		return $buf;

	}

	throw new \InvalidArgumentException('Not able to pack/serialize input type: '.gettype($input));
}

/**
 * Unpack data from a msgpack'ed string
 * 
 * @param string $input
 * @return mixed
 */
function msgpack_unpack($input)
{
	$bigendian = (pack('S',1)==pack('n',1));

	// Store input into a memory buffer so we can operate on it with filepointers
	static $buffer;
	if (!isset($buffer)) {
		$buffer = fopen('php://memory','w+');
		fwrite($buffer,$input);
		rewind($buffer);
	}

	// Read a single byte
	$byte = fread($buffer,1);
	
	// Re-open buffer on read error, probably EOF
	if ($byte === false || $byte === "") { 
		fclose($buffer);
		$buffer = fopen('php://memory','w+');
		fwrite($buffer,$input);
		rewind($buffer);
		$byte = fread($buffer,1);
	}

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

	// fixed raw
	if ((($byte ^ "\xA0") & "\xE0") == "\x00") {
		$len = current(unpack('c',($byte ^ "\xA0")));
		if ($len == 0) return "";
		return current(unpack('a'.$len,fread($buffer,$len)));
	}

	// Arrays
	if ((($byte ^ "\x90") & "\xF0") == "\x00") {
		// fixed array
		$len = current(unpack('c',($byte ^ "\x90")));
		$data = array();
		for($i=0;$i<$len;$i++) {
			$data[] = msgpack_unpack($input);
		}
		return $data;
	} else if ($byte == "\xDC" || $byte == "\xDD") {
		if ($byte == "\xDC") $len = current(unpack('n',fread($buffer,2)));
		if ($byte == "\xDD") $len = current(unpack('N',fread($buffer,4)));
		$data = array();
		for($i=0;$i<$len;$i++) {
			$data[] = msgpack_unpack($input);
		}
		return $data;
	}

	// Maps
	if ((($byte ^ "\x80") & "\xF0") == "\x00") {
		// fixed map
		$len = current(unpack('c',($byte ^ "\x80")));
		$data = array();
		for($i=0;$i<$len;$i++) {
			$key = msgpack_unpack($input);
			$value = msgpack_unpack($input);
			$data[$key] = $value;
		}
		return $data;
	} else if ($byte == "\xDE" || $byte == "\xDF") {
		if ($byte == "\xDE") $len = current(unpack('n',fread($buffer,2)));
		if ($byte == "\xDF") $len = current(unpack('N',fread($buffer,4)));
		$data = array();
		for($i=0;$i<$len;$i++) {
			$key = msgpack_unpack($input);
			$value = msgpack_unpack($input);
			$data[$key] = $value;
		}
		return $data;
	}

	switch ($byte) {
		// Unsigned integers
		case "\xCC": // uint 8
			return current(unpack('C',fread($buffer,1)));
		case "\xCD": // uint 16
			return current(unpack('n',fread($buffer,2)));
		case "\xCE": // uint 32
			return current(unpack('N',fread($buffer,4)));
		case "\xCF": // uint 64
			// Unpack into two uint32 and re-assemble
			$dat = unpack('Np1/Np2',fread($buffer,8));
			$dat['p1'] = $dat['p1'] << 32;
			return $dat['p1']|$dat['p2'];

			// Signed integers
		case "\xD0": // int 8
			return current(unpack('c',fread($buffer,1)));
		case "\xD1": // int 16
			return (current(unpack('n',~fread($buffer,2)))+1)*-1;
		case "\xD2": // int 32
			return (current(unpack('N',~fread($buffer,4)))+1)*-1;
		case "\xD3": // int 64
			$dat = unpack('Np1/Np2',~fread($buffer,8));
			$dat['p1'] = $dat['p1'] << 32;
			return (($dat['p1']|$dat['p2'])+1)*-1;
				
			// String / Raw
		case "\xDA": // raw 16
			$len = current(unpack('n',fread($buffer,2)));
			return current(unpack('a'.$len,fread($buffer,$len)));
		case "\xDB": // raw 32
			$len = current(unpack('N',fread($buffer,4)));
			return current(unpack('a'.$len,fread($buffer,$len)));
				
			// Floats
		case "\xCA": // single-precision
			return current(unpack('f',$bigendian ? fread($buffer,4) : strrev(fread($buffer,4))));
		case "\xCB": // double-precision
			return current(unpack('d',$bigendian ? fread($buffer,8): strrev(fread($buffer,8))));

	}

	throw new \InvalidArgumentException('Can\'t unpack data with byte-header: '.$byte);
}
