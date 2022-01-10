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

#include "util_for_PHP.h"
#include <php_main.h>
#include "util.h"
#include "time_util.h"
#include "ConfigManager.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_UTIL

ResultCode loadPhpFile( const char* filename )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "filename: `%s'", filename );

    ResultCode resultCode;
    size_t filename_len = strlen( filename );
    zval dummy;
    zend_file_handle file_handle;
    zend_op_array * new_op_array = NULL;
    zval result;
    int ret;
    zend_string* opened_path = NULL;

    if ( filename_len == 0 )
    {
        ELASTIC_APM_LOG_ERROR( "filename_len == 0" );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    ret = php_stream_open_for_zend_ex( filename, &file_handle, USE_PATH | STREAM_OPEN_FOR_INCLUDE );
    if ( ret != SUCCESS )
    {
        ELASTIC_APM_LOG_ERROR( "php_stream_open_for_zend_ex failed. Return value: %d. filename: %s", ret, filename );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    if ( ! file_handle.opened_path )
    {
        file_handle.opened_path = zend_string_init( filename, filename_len, 0 );
    }
    opened_path = zend_string_copy( file_handle.opened_path );
    ZVAL_NULL( &dummy );
    if ( ! zend_hash_add( &EG( included_files ), opened_path, &dummy ) )
    {
        ELASTIC_APM_LOG_ERROR( "zend_hash_add failed. filename: %s", filename );
        zend_file_handle_dtor( &file_handle );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    new_op_array = zend_compile_file( &file_handle, ZEND_REQUIRE );
    zend_destroy_file_handle( &file_handle );
    if ( ! new_op_array )
    {
        ELASTIC_APM_LOG_ERROR( "zend_compile_file failed. filename: %s", filename );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
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

    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    return resultCode;

    failure:
    goto finally;
}

void getArgsFromZendExecuteData( zend_execute_data* execute_data, size_t dstArraySize, zval dstArray[], uint32_t* argsCount )
{
    *argsCount = ZEND_CALL_NUM_ARGS( execute_data );
    ZEND_PARSE_PARAMETERS_START( /* min_num_args: */ 0, /* max_num_args: */ ( (int) dstArraySize ) )
    Z_PARAM_OPTIONAL
    ELASTIC_APM_FOR_EACH_INDEX( i, *argsCount )
    {
        zval* pArgAsZval;
        Z_PARAM_ZVAL( pArgAsZval )
        dstArray[ i ] = *pArgAsZval;
    }
    ZEND_PARSE_PARAMETERS_END();
}

typedef void (* ConsumeZvalFunc)( void* ctx, const zval* pZval );

static
ResultCode callPhpFunction( StringView phpFunctionName, uint32_t argsCount, zval args[], ConsumeZvalFunc consumeRetVal, void* consumeRetValCtx )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "phpFunctionName: `%.*s', argsCount: %u"
                                              , (int) phpFunctionName.length, phpFunctionName.begin, argsCount );

    ResultCode resultCode;
    zval phpFunctionNameAsZval;
    ZVAL_UNDEF( &phpFunctionNameAsZval );
    zval phpFunctionRetVal;
    ZVAL_UNDEF( &phpFunctionRetVal );

    ZVAL_STRINGL( &phpFunctionNameAsZval, phpFunctionName.begin, phpFunctionName.length );
    // call_user_function(function_table, object, function_name, retval_ptr, param_count, params)
    int callUserFunctionRetVal = call_user_function(
            EG( function_table )
            , /* object: */ NULL
            , /* function_name: */ &phpFunctionNameAsZval
            , /* retval_ptr: */ &phpFunctionRetVal
            , argsCount
            , args );
    if ( callUserFunctionRetVal != SUCCESS )
    {
        ELASTIC_APM_LOG_ERROR( "call_user_function failed. Return value: %d. PHP function name: `%.*s'. argsCount: %u."
                , callUserFunctionRetVal, (int) phpFunctionName.length, phpFunctionName.begin, argsCount );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    if ( consumeRetVal != NULL ) consumeRetVal( consumeRetValCtx, &phpFunctionRetVal );

    resultCode = resultSuccess;

    finally:
    zval_dtor( &phpFunctionNameAsZval );
    zval_dtor( &phpFunctionRetVal );

    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    return resultCode;

    failure:
    goto finally;
}

static
void consumeBoolRetVal( void* ctx, const zval* pZval )
{
    ELASTIC_APM_ASSERT_VALID_PTR( ctx );
    ELASTIC_APM_ASSERT_VALID_PTR( pZval );

    if ( Z_TYPE_P( pZval ) == IS_TRUE )
    {
        *((bool*)ctx) = true;
    }
    else
    {
        ELASTIC_APM_ASSERT( Z_TYPE_P( pZval ) == IS_FALSE, "Z_TYPE_P( pZval ) as int: %d", (int) ( Z_TYPE_P( pZval ) ) );
        *((bool*)ctx) = false;
    }
}

ResultCode callPhpFunctionRetBool( StringView phpFunctionName, uint32_t argsCount, zval args[], bool* retVal )
{
    return callPhpFunction( phpFunctionName, argsCount, args, consumeBoolRetVal, /* consumeRetValCtx: */ retVal );
}

ResultCode callPhpFunctionRetVoid( StringView phpFunctionName, uint32_t argsCount, zval args[] )
{
    return callPhpFunction( phpFunctionName, argsCount, args, /* consumeRetValCtx: */ NULL, /* consumeRetValCtx: */ NULL );
}

static
void consumeZvalRetVal( void* ctx, const zval* pZval )
{
    ELASTIC_APM_ASSERT_VALID_PTR( ctx );
    ELASTIC_APM_ASSERT_VALID_PTR( pZval );

    ZVAL_COPY( ((zval*)ctx), pZval );
}

ResultCode callPhpFunctionRetZval( StringView phpFunctionName, uint32_t argsCount, zval args[], zval* retVal )
{
    return callPhpFunction( phpFunctionName, argsCount, args, consumeZvalRetVal, retVal );
}
