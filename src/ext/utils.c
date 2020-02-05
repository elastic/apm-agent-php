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

#include "utils.h"

#include <stdio.h>
#include <ext/standard/php_rand.h>


static void genRandomIdBinary( Byte* buffer, UInt8 idSizeBytes )
{
    FOR_EACH_INDEX( UInt8, i, idSizeBytes ) buffer[ i ] = (Byte) ( php_mt_rand_range( 0, UINT8_MAX ) );
}

static ResultCode binaryIdToHexString( const Byte* idBinary, UInt8 idSizeBytes, String* pResult )
{
    ResultCode resultCode;
    const size_t resultLength = sizeof( char ) * ( idSizeBytes * 2 );
    MutableString result = NULL;

    ALLOC_STRING_IF_FAILED_GOTO( resultLength, result );
    FOR_EACH_INDEX( UInt8, i, idSizeBytes ) sprintf( result + i * 2, "%02lx", (long) ( idBinary[ i ] ) );
    result[ resultLength ] = '\0';

    resultCode = resultSuccess;
    *pResult = result;

    finally:
    return resultCode;

    failure:
    EFREE_AND_SET_TO_NULL( result );
    goto finally;
}

ResultCode genRandomIdHexString( UInt8 idSizeBytes, String* pResult )
{
    ResultCode resultCode;
    Byte* idBinary = NULL;
    String result = NULL;

    ALLOC_ARRAY_IF_FAILED_GOTO( Byte, idSizeBytes, idBinary );
    genRandomIdBinary( idBinary, idSizeBytes );
    CALL_IF_FAILED_GOTO( binaryIdToHexString( idBinary, idSizeBytes, &result ) );

    resultCode = resultSuccess;
    *pResult = result;

    finally:
    EFREE_AND_SET_TO_NULL( idBinary );
    return resultCode;

    failure:
    EFREE_AND_SET_TO_NULL( result );
    goto finally;
}

#ifdef PHP_WIN32
void* g_unusedParameterHelper;
#endif

