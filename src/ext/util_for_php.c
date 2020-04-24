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

#include "util_for_php.h"
#include <php_main.h>

ResultCode loadPhpFile( const char* filename TSRMLS_DC )
{
    ELASTICAPM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "filename: `%s'", filename );

    ResultCode resultCode;
    size_t filename_len = strlen( filename );
    zval dummy;
    zend_file_handle file_handle;
    zend_op_array* new_op_array = NULL;
    zval result;
    int ret;
    zend_string* opened_path = NULL;

    if ( filename_len == 0 )
    {
        ELASTICAPM_LOG_ERROR( "filename_len == 0" );
        resultCode = resultFailure;
        goto failure;
    }

    ret = php_stream_open_for_zend_ex( filename, &file_handle, USE_PATH | STREAM_OPEN_FOR_INCLUDE );
    if ( ret != SUCCESS )
    {
        ELASTICAPM_LOG_ERROR( "php_stream_open_for_zend_ex failed. Return value: %d. filename: %s", ret, filename );
        resultCode = resultFailure;
        goto failure;
    }

    if ( ! file_handle.opened_path )
    {
        file_handle.opened_path = zend_string_init( filename, filename_len, 0 );
    }
    opened_path = zend_string_copy( file_handle.opened_path );
    ZVAL_NULL( &dummy );
    if ( ! zend_hash_add( &EG( included_files ), opened_path, &dummy ) )
    {
        ELASTICAPM_LOG_ERROR( "zend_hash_add failed. filename: %s", filename );
        zend_file_handle_dtor( &file_handle );
        resultCode = resultFailure;
        goto failure;
    }

    new_op_array = zend_compile_file( &file_handle, ZEND_REQUIRE );
    zend_destroy_file_handle( &file_handle );
    if ( ! new_op_array )
    {
        ELASTICAPM_LOG_ERROR( "zend_compile_file failed. filename: %s", filename );
        resultCode = resultFailure;
        goto failure;
    }

    ZVAL_UNDEF( &result );
    zend_execute( new_op_array, &result );
    zval_ptr_dtor( &result );

    resultCode = resultSuccess;

    finally:
    if ( new_op_array )
    {
        destroy_op_array( new_op_array );
        efree( new_op_array );
        new_op_array = NULL;
    }

    if ( opened_path )
    {
        zend_string_release( opened_path );
        opened_path = NULL;
    }

    ELASTICAPM_LOG_DEBUG_FUNCTION_EXIT_MSG( "resultCode: %s (%d)", resultCodeToString( resultCode ), resultCode );
    return resultCode;

    failure:
    goto finally;
}

void getArgsFromZendExecuteData( zend_execute_data *execute_data, size_t dstArraySize, zval dstArray[], uint32_t* argsCount )
{
    *argsCount = ZEND_CALL_NUM_ARGS( execute_data );
    ZEND_PARSE_PARAMETERS_START( /* min_num_args: */ 0, /* max_num_args: */ ((int)dstArraySize) )
    Z_PARAM_OPTIONAL
    ELASTICAPM_FOR_EACH_INDEX( i, *argsCount )
    {
        zval* pArgAsZval;
        Z_PARAM_ZVAL( pArgAsZval )
        dstArray[ i ] = *pArgAsZval;
    }
    ZEND_PARSE_PARAMETERS_END();
}

ResultCode callPhpFunctionEx( StringView phpFunctionName, LogLevel logLevel, uint32_t argsCount, zval args[] )
{
    ELASTICAPM_LOG_FUNCTION_ENTRY_MSG_WITH_LEVEL( logLevel, "phpFunctionName: `%.*s', argsCount: %u"
            , (int) phpFunctionName.length, phpFunctionName.begin, argsCount );

    ResultCode resultCode;
    zval phpFunctionCallRetVal;
    zval phpFunctionNameAsZval;
    ZVAL_UNDEF( &phpFunctionNameAsZval );
    ZVAL_UNDEF( &phpFunctionCallRetVal );

    ZVAL_STRINGL( &phpFunctionNameAsZval, phpFunctionName.begin, phpFunctionName.length );
    // call_user_function(function_table, object, function_name, retval_ptr, param_count, params)
    int callUserFunctionRetVal = call_user_function(
            EG( function_table )
            , /* object: */ NULL
            , /* function_name: */ &phpFunctionNameAsZval
            , /* retval_ptr: */ &phpFunctionCallRetVal
            , argsCount
            , args TSRMLS_CC );
    if ( callUserFunctionRetVal != SUCCESS )
    {
        ELASTICAPM_LOG_ERROR( "call_user_function failed. Return value: %d", callUserFunctionRetVal );
        resultCode = resultFailure;
        goto failure;
    }

    resultCode = resultSuccess;

    finally:
    zval_dtor( &phpFunctionNameAsZval );
    zval_dtor( &phpFunctionCallRetVal );

    ELASTICAPM_LOG_FUNCTION_EXIT_MSG_WITH_LEVEL( logLevel, "resultCode: %s (%d)", resultCodeToString( resultCode ), resultCode );
    return resultCode;

    failure:
    goto finally;
}

ResultCode callPhpFunction( StringView phpFunctionName, LogLevel logLevel )
{
    return callPhpFunctionEx( phpFunctionName, logLevel, /* argsCount: */ 0, /* args: */ NULL );
}
