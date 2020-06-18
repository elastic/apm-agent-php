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

#include <stdbool.h>
#include <php.h>
#include <zend.h>
#include "elasticapm_assert.h"
#include "basic_types.h"
#include "ResultCode.h"
#include "MemoryTracker.h"
#include "ConfigManager.h"

static inline
bool isEmtpyZstring( const zend_string* zStr )
{
    return ZSTR_LEN( zStr ) == 0;
}

static inline
bool isNullOrEmtpyZstring( const zend_string* zStr )
{
    return zStr == NULL || isEmtpyZstring( zStr );
}

static inline
bool isZarray( const zval* zValue )
{
    ELASTICAPM_ASSERT_VALID_PTR( zValue );

    return Z_TYPE_P( zValue ) == IS_ARRAY;
}

static inline
const zval* findInZarrayByStringKey( const zend_array* zArray, StringView key )
{
    return zend_hash_str_find( zArray, key.begin, key.length );
}

ResultCode loadPhpFile( const char* filename TSRMLS_DC );
ResultCode callPhpFunctionRetBool( StringView phpFunctionName, LogLevel logLevel, uint32_t argsCount, zval args[], bool* retVal );
ResultCode callPhpFunctionRetVoid( StringView phpFunctionName, LogLevel logLevel, uint32_t argsCount, zval args[] );
ResultCode callPhpFunctionRetZval( StringView phpFunctionName, LogLevel logLevel, uint32_t argsCount, zval args[], zval* retVal );

void getArgsFromZendExecuteData( zend_execute_data *execute_data, size_t dstArraySize, zval dstArray[], uint32_t* argsCount );

ResultCode sendEventsToApmServer( const ConfigSnapshot* config, String serializedEvents );