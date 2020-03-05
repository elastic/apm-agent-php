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
#include <php_main.h>
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

ResultCode elasticApmExecutePhpFile( const char *filename TSRMLS_DC ) {
    ResultCode resultCode = resultFailure;
    int filename_len = strlen(filename);
    zval dummy;
    zend_file_handle file_handle;
    zend_op_array *new_op_array = NULL;
    zval result;
    int ret;
    zend_string *opened_path = NULL;

    if (filename_len == 0) {
        goto failure;
    }

    ret = php_stream_open_for_zend_ex(filename, &file_handle, USE_PATH | STREAM_OPEN_FOR_INCLUDE);

    if (ret == SUCCESS) {
        
        if (!file_handle.opened_path) {
            file_handle.opened_path = zend_string_init(filename, filename_len, 0);
        }
        opened_path = zend_string_copy(file_handle.opened_path);
        ZVAL_NULL(&dummy);
        if (zend_hash_add(&EG(included_files), opened_path, &dummy)) {
            new_op_array = zend_compile_file(&file_handle, ZEND_REQUIRE);
            zend_destroy_file_handle(&file_handle);
        } else {
            new_op_array = NULL;
            zend_file_handle_dtor(&file_handle);
        }
        zend_string_release(opened_path);
        opened_path = NULL;
        if (new_op_array) {
            ZVAL_UNDEF(&result);

            zend_execute(new_op_array, &result);

            destroy_op_array(new_op_array);
            new_op_array = NULL;
            zval_ptr_dtor(&result);

            resultCode = resultSuccess;
        }
    } 

    finally:
    EFREE_AND_SET_TO_NULL( new_op_array );
    EFREE_AND_SET_TO_NULL( opened_path );
    return resultCode;

    failure:
    goto finally;
}

#ifdef PHP_WIN32
void* g_unusedParameterHelper;
#endif

