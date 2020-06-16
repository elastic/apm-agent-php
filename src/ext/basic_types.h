/*
   +----------------------------------------------------------------------+
   | Elastic APM agent for PHP                                            |
   +----------------------------------------------------------------------+
   | Copyright (c) 2020 Elasticsearch B.V.                                |
   +----------------------------------------------------------------------+
   | Elasticsearch B.V. licenses this file under the Apache 2.0 License.  |
   | See the LICENSE file in the project root for more information.       |
   +----------------------------------------------------------------------+
 */

#pragma once

#include <stdint.h>
#include <sys/types.h>
#include <string.h>

typedef unsigned int UInt;
typedef uint8_t UInt8;
typedef int8_t Int8;
typedef uint16_t UInt16;
typedef int16_t Int16;
typedef uint32_t UInt32;
typedef int32_t Int32;
typedef uint64_t UInt64;
typedef int64_t Int64;

typedef UInt8 Byte;

typedef const char* String;
typedef char* MutableString;
