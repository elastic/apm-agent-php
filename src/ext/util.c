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

#include "util.h"
#include <stdlib.h>
#include <stdio.h>
#include <php.h>
#include <ext/standard/php_rand.h>
#include "constants.h"
#include "ConfigManager.h"

#define ELASTICAPM_CURRENT_LOG_CATEGORY ELASTICAPM_CURRENT_LOG_CATEGORY_UTIL

static
void genRandomIdBinary( Byte* buffer, UInt8 idSizeBytes )
{
    ELASTICAPM_FOR_EACH_INDEX_EX( UInt8, i, idSizeBytes ) buffer[ i ] = (Byte) ( php_mt_rand_range( 0, UINT8_MAX ) );
}

static
void binaryIdToHexString( const Byte* idBinary, UInt8 idSizeBytes, char* idAsHexStringBuffer, size_t idAsHexStringBufferSize )
{
    ELASTICAPM_ASSERT_VALID_PTR( idAsHexStringBuffer );

    const size_t idAsHexStringLen = ELASTICAPM_CALC_ID_AS_HEX_STRING_BUFFER_SIZE( idSizeBytes ) - 1;
    // +1 for terminating '\0'
    ELASTICAPM_ASSERT_GE_UINT64( idAsHexStringBufferSize, ( idAsHexStringLen + 1 ) );

    ELASTICAPM_FOR_EACH_INDEX_EX( UInt8, i, idSizeBytes )
        sprintf( idAsHexStringBuffer + i * 2, "%02x", (UInt) ( idBinary[ i ] ) );
    idAsHexStringBuffer[ idAsHexStringLen ] = '\0';
}

void genRandomIdAsHexString( UInt8 idSizeBytes, char* idAsHexStringBuffer, size_t idAsHexStringBufferSize )
{
    ELASTICAPM_ASSERT_LE_UINT64( idSizeBytes, idMaxSizeInBytes );
    ELASTICAPM_ASSERT_VALID_PTR( idAsHexStringBuffer );
    // +1 for terminating '\0'
    ELASTICAPM_ASSERT_GE_UINT64( idAsHexStringBufferSize, ELASTICAPM_CALC_ID_AS_HEX_STRING_BUFFER_SIZE( idSizeBytes ) );

    Byte idBinary[ idMaxSizeInBytes ];

    genRandomIdBinary( idBinary, idSizeBytes );
    binaryIdToHexString( idBinary, idSizeBytes, idAsHexStringBuffer, idAsHexStringBufferSize );
}
