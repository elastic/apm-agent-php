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
#include <stdio.h>
#include <php_main.h>
#include <zend_hash.h>
#include <zend_compile.h>
#include "util.h"
#include "platform.h"
#include "time_util.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_UTIL

void logDiagnostics_for_failed_php_stream_open_for_zend_ex( const char* phpFilePath )
{
    FILE* fopen_ret_val = fopen( phpFilePath, "r" );
    int fopen_errno = errno;
    const char* prefix = "Diagnostics for failed php_stream_open_for_zend_ex()";
    if ( fopen_ret_val == NULL )
    {
        char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
        TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
        ELASTIC_APM_LOG_ERROR( "%s: fopen(\"%s\", \"r\") returned NULL value, errno: %d (%s)", prefix, phpFilePath, fopen_errno, streamErrNo( fopen_errno, &txtOutStream ) );
    }
    else
    {
        ELASTIC_APM_LOG_ERROR( "%s: fopen(\"%s\", \"r\") returned non-NULL value", prefix, phpFilePath );
        fclose( fopen_ret_val );
    }
}

ResultCode loadPhpFile( const char* phpFilePath )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "phpFilePath: `%s'", phpFilePath );

    ResultCode resultCode;
    zval dummy;
    zend_file_handle file_handle;
    bool should_destroy_file_handle = false;
    zend_string* opened_path = NULL;
    zend_op_array* new_op_array = NULL;
    zval result;
    bool should_dtor_result = false;

    size_t phpFilePathLen = strlen( phpFilePath );
    if ( phpFilePathLen == 0 )
    {
        ELASTIC_APM_LOG_ERROR( "phpFilePathLen == 0" );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    // Copied from
    //      https://github.com/php/php-src/blob/php-8.0.0/ext/spl/php_spl.c
    //      https://github.com/php/php-src/blob/php-8.1.0/ext/spl/php_spl.c
    // the second half of spl_autoload()

#       if PHP_VERSION_ID >= ELASTIC_APM_BUILD_PHP_VERSION_ID( 8, 1, 0 ) /* if PHP version from 8.1.0 */
    zend_string* phpFilePathAsZendString = zend_string_init( phpFilePath, phpFilePathLen, /* persistent: */ 0 );
    zend_stream_init_filename_ex( &file_handle, phpFilePathAsZendString );
    should_destroy_file_handle = true;
#       endif

    int php_stream_open_for_zend_ex_retVal = php_stream_open_for_zend_ex(
#               if PHP_VERSION_ID < ELASTIC_APM_BUILD_PHP_VERSION_ID( 8, 1, 0 ) /* if PHP version before 8.1.0 */
            phpFilePath,
#               endif
            &file_handle
            , USE_PATH|STREAM_OPEN_FOR_INCLUDE );
    if ( php_stream_open_for_zend_ex_retVal != SUCCESS )
    {
        ELASTIC_APM_LOG_ERROR( "php_stream_open_for_zend_ex() failed. Return value: %d. phpFilePath: `%s'", php_stream_open_for_zend_ex_retVal, phpFilePath );
        logDiagnostics_for_failed_php_stream_open_for_zend_ex( phpFilePath );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }
    should_destroy_file_handle = true;

    if ( ! file_handle.opened_path ) {
        file_handle.opened_path =
#               if PHP_VERSION_ID < ELASTIC_APM_BUILD_PHP_VERSION_ID( 8, 1, 0 ) /* if PHP version before 8.1.0 */
            zend_string_init( phpFilePath, phpFilePathLen, /* persistent: */ 0 );
#               else
            zend_string_copy( phpFilePathAsZendString );
#               endif
    }
    opened_path = zend_string_copy( file_handle.opened_path );

    ZVAL_NULL( &dummy );
    if ( ! zend_hash_add( &EG(included_files), opened_path, &dummy ) )
    {
#           if PHP_VERSION_ID < ELASTIC_APM_BUILD_PHP_VERSION_ID( 8, 1, 0 ) /* if PHP version before 8.1.0 */
        zend_file_handle_dtor( &file_handle );
        should_destroy_file_handle = false;
#           endif

        ELASTIC_APM_LOG_ERROR( "zend_hash_add failed. phpFilePath: `%s'", phpFilePath );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    new_op_array = zend_compile_file( &file_handle, ZEND_REQUIRE );
    if ( new_op_array == NULL )
    {
        ELASTIC_APM_LOG_ERROR( "zend_compile_file failed. phpFilePath: `%s'", phpFilePath );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    ZVAL_UNDEF( &result );
    zend_execute( new_op_array, &result );
    should_dtor_result = true;
    bool hasThrownException = ( EG( exception ) != NULL );
    destroy_op_array( new_op_array );
    efree( new_op_array );
    if ( hasThrownException )
    {
        should_dtor_result = false;
        ELASTIC_APM_LOG_ERROR( "Exception was thrown during zend_execute(). phpFilePath: `%s'", phpFilePath );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    resultCode = resultSuccess;

    finally:

    if ( should_dtor_result )
    {
        zval_ptr_dtor( &result );
    }

    if ( opened_path != NULL )
    {
#           if PHP_VERSION_ID < ELASTIC_APM_BUILD_PHP_VERSION_ID( 7, 3, 0 ) /* if PHP version before 7.3.0 */
        zend_string_release( opened_path );
#else
        zend_string_release_ex( opened_path, /* persistent: */ 0 );
#           endif
        opened_path = NULL;
    }

    if ( should_destroy_file_handle )
    {
        zend_destroy_file_handle( &file_handle );
        should_destroy_file_handle = false;
    }

#       if PHP_VERSION_ID >= ELASTIC_APM_BUILD_PHP_VERSION_ID( 8, 1, 0 ) /* if PHP version from 8.1.0 */
    zend_string_release( phpFilePathAsZendString );
#       endif

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

bool isPhpRunningAsCliScript()
{
    return strcmp( sapi_module.name, "cli" ) == 0;
}

int call_internal_function(zval *object, const char *functionName, zval parameters[], int32_t parametersCount, zval *returnValue) {
	zval funcName;
	ZVAL_STRING(&funcName, functionName);

	int result = resultFailure;
	zend_try {
#if PHP_VERSION_ID >= 80000
		result = _call_user_function_impl(object, &funcName, returnValue, parametersCount, parameters, NULL);
#else
		result = _call_user_function_ex(object, &funcName, returnValue, parametersCount, parameters, 0);
#endif
	} zend_catch {
        ELASTIC_APM_LOG_ERROR("Call of '%s' failed", functionName);
	} zend_end_try();

	zval_ptr_dtor(&funcName);
	return result;
}

bool isOpcacheEnabled() {
    return isPhpRunningAsCliScript() ? INI_BOOL("opcache.enable_cli") : INI_BOOL("opcache.enable");
}

bool isScriptRestricedByOpcacheAPI() {
    if (!isOpcacheEnabled()) {
        return false;
    }

    char *restrict_api = INI_STR("opcache.restrict_api");
    if (!restrict_api || strlen(restrict_api) == 0) {
        return false;
    }

    size_t len = strlen(restrict_api);
    if (!SG(request_info).path_translated ||
        strlen(SG(request_info).path_translated) < len ||
        memcmp(SG(request_info).path_translated, restrict_api, len) != 0) {
        ELASTIC_APM_LOG_DEBUG("Script '%s' is restricted by \"opcache.restrict_api\" configuration directive. Can't perform any opcache API calls.", SG(request_info).path_translated);
        return true;
    }
    return false;
}

bool detectOpcacheRestartPending() {
    if (!isOpcacheEnabled()) {
        return false;
    }
    if (EG(function_table) && !zend_hash_str_find_ptr(EG(function_table), ZEND_STRL("opcache_get_status"))) {
        return false;
    }

	zval rv;
    ZVAL_NULL(&rv);
	zval parameters[1];
	ZVAL_BOOL(&parameters[0], false);

	int er = EG(error_reporting); // suppress error/warning reporing
	EG(error_reporting) = 0;
    int result = call_internal_function(NULL, "opcache_get_status", parameters, 1, &rv);
	EG(error_reporting) = er;

    if (result == resultFailure) {
        ELASTIC_APM_LOG_ERROR("opcache_get_status failure");
        zval_ptr_dtor(&rv);
        return false;
    }

    if (Z_TYPE(rv) != IS_ARRAY) {
        ELASTIC_APM_LOG_DEBUG("opcache_get_status failed, rvtype: %d", Z_TYPE(rv));
        zval_ptr_dtor(&rv);
        return false;
    }

	zval *restartPending = zend_hash_str_find(Z_ARRVAL(rv), ZEND_STRL("restart_pending"));
    if (restartPending && Z_TYPE_P(restartPending) == IS_TRUE) {
        zval_ptr_dtor(&rv);
        return true;
    } else if (!restartPending || Z_TYPE_P(restartPending) != IS_FALSE) {
        ELASTIC_APM_LOG_DEBUG("opcache_get_status returned unexpected data ptr: %p t:%d", restartPending, restartPending ? Z_TYPE_P(restartPending) : -1);
    }

    zval_ptr_dtor(&rv);
    return false;
}


bool detectOpcachePreload() {
    if (PHP_VERSION_ID < 70400) {
        return false;
    }

    if (!isOpcacheEnabled()) {
        return false;
    }

    const char *preloadValue = INI_STR("opcache.preload");
    if (!preloadValue || strlen(preloadValue) == 0) {
        return false;
    }

    // lookup for opcache_get_status
    if (EG(function_table) && !zend_hash_str_find_ptr(EG(function_table), ZEND_STRL("opcache_get_status"))) {
        return false;
    }

    zval *server = zend_hash_str_find(&EG(symbol_table), ZEND_STRL("_SERVER"));
    if (!server || Z_TYPE_P(server) != IS_ARRAY) {
        return true; // actually should be available in preload
    }

    // not available in preload request
    zval *script = zend_hash_str_find(Z_ARRVAL_P(server), ZEND_STRL("SCRIPT_NAME"));
    if (!script) {
        return true;
    }
    return false;
}

void enableAccessToServerGlobal() {
    zend_is_auto_global_str(ZEND_STRL("_SERVER"));
}

String streamZVal( const zval* zVal, TextOutputStream* txtOutStream )
{
    if ( zVal == NULL )
    {
        return "NULL";
    }

    int zValType = (int)Z_TYPE_P( zVal );
    switch ( zValType )
    {
        case IS_STRING:
        {
            StringView strVw = zStringToStringView( Z_STR_P( zVal ) );
            return streamPrintf( txtOutStream, "type: string, value [length: %"PRIu64"]: %.*s", (UInt64)(strVw.length), (int)(strVw.length), strVw.begin );
        }

        case IS_LONG:
            return streamPrintf( txtOutStream, "type: long, value: %"PRId64, (Int64)(Z_LVAL_P( zVal )) );

        case IS_DOUBLE:
            return streamPrintf( txtOutStream, "type: double, value: %f", (double)(Z_DVAL_P( zVal )) );

        case IS_NULL:
            return streamPrintf( txtOutStream, "type: null" );

        case IS_FALSE:
            return streamPrintf( txtOutStream, "type: false" );
        case IS_TRUE:
            return streamPrintf( txtOutStream, "type: true " );

        default:
            return streamPrintf( txtOutStream, "type: %s (type ID as int: %d)", zend_get_type_by_const( zValType ), (int)zValType );
    }
}
