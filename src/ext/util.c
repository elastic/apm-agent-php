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

#include "util.h"
#include <stdlib.h>
#include <stdio.h>
#include <php.h>
#include <ext/standard/php_rand.h>
#include "constants.h"
#include "ConfigManager.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_UTIL

static
void genRandomIdBinary( Byte* buffer, UInt8 idSizeBytes )
{
    ELASTIC_APM_FOR_EACH_INDEX_EX( UInt8, i, idSizeBytes ) buffer[ i ] = (Byte) ( php_mt_rand_range( 0, UINT8_MAX ) );
}

static
void binaryIdToHexString( const Byte* idBinary, UInt8 idSizeBytes, char* idAsHexStringBuffer, size_t idAsHexStringBufferSize )
{
    ELASTIC_APM_ASSERT_VALID_PTR( idAsHexStringBuffer );

    const size_t idAsHexStringLen = ELASTIC_APM_CALC_ID_AS_HEX_STRING_BUFFER_SIZE( idSizeBytes ) - 1;
    // +1 for terminating '\0'
    ELASTIC_APM_ASSERT_GE_UINT64( idAsHexStringBufferSize, ( idAsHexStringLen + 1 ) );

    ELASTIC_APM_FOR_EACH_INDEX_EX( UInt8, i, idSizeBytes )
        sprintf( idAsHexStringBuffer + i * 2, "%02x", (UInt) ( idBinary[ i ] ) );
    idAsHexStringBuffer[ idAsHexStringLen ] = '\0';
}

void genRandomIdAsHexString( UInt8 idSizeBytes, char* idAsHexStringBuffer, size_t idAsHexStringBufferSize )
{
    ELASTIC_APM_ASSERT_LE_UINT64( idSizeBytes, idMaxSizeInBytes );
    ELASTIC_APM_ASSERT_VALID_PTR( idAsHexStringBuffer );
    // +1 for terminating '\0'
    ELASTIC_APM_ASSERT_GE_UINT64( idAsHexStringBufferSize, ELASTIC_APM_CALC_ID_AS_HEX_STRING_BUFFER_SIZE( idSizeBytes ) );

    Byte idBinary[ idMaxSizeInBytes ];

    genRandomIdBinary( idBinary, idSizeBytes );
    binaryIdToHexString( idBinary, idSizeBytes, idAsHexStringBuffer, idAsHexStringBufferSize );
}
