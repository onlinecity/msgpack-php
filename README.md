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
 
 
Benchmark
-----

Based on [msgpack/php/benchmark.php](https://github.com/msgpack/msgpack/blob/master/php/benchmark.php)

As you can see the pure PHP implementation (msgpack-php) is quite slow compared with the great php-extension msgpack has created. 
However when msgpack-php is compiled using HipHop (msgpack-hphp) the performance difference is much less, with msgpack-hphp even beating msgpack in this particular benchmark a few places, perhaps due to the fact that the benchmark itself is compiled with HipHop as well.

The timing values are average seconds sampled 10 times with 10.000 iterations each. Percentages are relative to normal php serialize() (not HipHop's version).


```
[integer   ]       default          json      igbinary       msgpack   msgpack-php   msgpack-hphp
status     :           OK           OK           OK           OK           OK          OK
serialize  : 0.0105 (100%) 0.0090 ( 85%) 0.0089 ( 84%) 0.0093 ( 88%) 0.0550 ( 523%) 0.0051 ( 57%)
unserialize: 0.0089 (100%) 0.0119 (134%) 0.0088 ( 99%) 0.0090 (101%) 0.0985 (1110%) 0.0208 (234%)
size       :     12 (100%)      9 ( 75%)      9 ( 75%)      5 ( 41%)      5 (  41%)      5 ( 41%)

[double    ]       default          json      igbinary       msgpack   msgpack-php   msgpack-hphp
status     :           OK           OK           OK           OK           OK          OK
serialize  : 0.0323 (100%) 0.0132 ( 40%) 0.0092 ( 28%) 0.0094 ( 28%) 0.0740 (229%)  0.0074 ( 23%)
unserialize: 0.0221 (100%) 0.0124 ( 56%) 0.0085 ( 38%) 0.0089 ( 40%) 0.1151 (521%)  0.0217 ( 98%)
size       :     54 (100%)     15 ( 27%)     13 ( 24%)      9 ( 16%)      9 ( 16%)       9 ( 16%)

[string    ]       default          json      igbinary       msgpack   msgpack-php   msgpack-hphp
status     :           OK           OK           OK           OK           OK          OK
serialize  : 0.0104 (100%) 0.0123 (118%) 0.0092 ( 88%) 0.0096 ( 91%) 0.0728 ( 700%) 0.0067 ( 64%)
unserialize: 0.0095 (100%) 0.0153 (160%) 0.0093 ( 97%) 0.0097 (101%) 0.1469 (1543%) 0.0301 (316%)
size       :     40 (100%)     34 ( 85%)     38 ( 95%)     35 ( 87%)     35 (  87%)     35 ( 87%)

[array     ]       default          json      igbinary       msgpack   msgpack-php   msgpack-hphp
status     :           OK           OK           OK           OK           OK          OK
serialize  : 0.0183 (100%) 0.0297 (162%) 0.0222 (121%) 0.0134 ( 73%) 0.5567 (3042%) 0.0432 (236%)
unserialize: 0.0174 (100%) 0.0363 (207%) 0.0171 ( 98%) 0.0170 ( 97%) 0.7823 (4483%) 0.1665 (957%)
size       :    226 (100%)    176 ( 77%)    186 ( 82%)    176 ( 77%)    176 (  77%)    176 ( 77%)

[array     ]       default          json      igbinary       msgpack    msgpack-php   msgpack-hphp
status     :           OK           OK           OK           OK           OK           OK
serialize  : 0.0199 (100%) 0.0481 (242%) 0.0306 (153%) 0.0148 ( 74%) 0.8513 (4285%) 0.0763 ( 383%)
unserialize: 0.0231 (100%) 0.0583 (252%) 0.0202 ( 87%) 0.0248 (107%) 1.4168 (6134%) 0.3156 (1366%)
size       :    406 (100%)    351 ( 86%)    346 ( 85%)    351 ( 86%)    351 (  86%)    351 (  86%)
```