/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

#pragma once

#include <stdbool.h>
#include <php.h>
#include <zend.h>
#include "elastic_apm_assert.h"
#include "basic_types.h"
#include "log.h"
#include "MemoryTracker.h"
#include "ResultCode.h"

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
    ELASTIC_APM_ASSERT_VALID_PTR( zValue );

    return Z_TYPE_P( zValue ) == IS_ARRAY;
}

static inline
const zval* findInZarrayByStringKey( const zend_array* zArray, StringView key )
{
    return zend_hash_str_find( zArray, key.begin, key.length );
}

ResultCode loadPhpFile( const char* filename );
ResultCode callPhpFunctionRetBool( StringView phpFunctionName, uint32_t argsCount, zval args[], bool* retVal );
ResultCode callPhpFunctionRetVoid( StringView phpFunctionName, uint32_t argsCount, zval args[] );
ResultCode callPhpFunctionRetZval( StringView phpFunctionName, uint32_t argsCount, zval args[], zval* retVal );

void getArgsFromZendExecuteData( zend_execute_data *execute_data, size_t dstArraySize, zval dstArray[], uint32_t* argsCount );
