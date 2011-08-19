MessagePack PHP functions
=============

The purpose of this project is to implement [MessagePack](http://msgpack.org/) serialization using only PHP. This might be useful for someone unable to install php-modules, or using [HipHop](https://github.com/facebook/hiphop-php) to compile PHP as C++.  

Caveats
-----

 - Only msgpack_pack() and msgpack_unpack() are defined.
 - It's only tested on [little endian](http://en.wikipedia.org/wiki/Endianness) architecture, but should work on big endian as well, please test it if able. 
 - The uint64 and int64 types probably requires 64-bit systems to work
 - It uses is_x() to select the type, do your casts before using the functions
 - Unlike the official lib you can't pack objects. If you know how to do this please fork.
 - It will always pack integers into the least amount of bits possible, and will prefer unsigned.