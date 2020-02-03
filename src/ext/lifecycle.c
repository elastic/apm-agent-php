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

#include "lifecycle.h"

#include <inttypes.h>
#include <stdbool.h>
#include <php.h>
#include <Zend/zend.h>
#include <Zend/zend_exceptions.h>
#include <ext/spl/spl_exceptions.h>

#if defined(PHP_WIN32) && !defined(CURL_STATICLIB)
#   define CURL_STATICLIB
#endif

#include <curl/curl.h>

#include "php_elasticapm.h"
#include "log.h"
#include "constants.h"
#include "SystemMetrics.h"
#include "php_error.h"
#include "utils.h"

static const char JSON_METADATA[] =
        "{\"metadata\":{\"process\":{\"pid\":%d},\"service\":{\"name\":\"%s\",\"language\":{\"name\":\"php\"},\"agent\":{\"version\":\"%s\",\"name\":\"apm-agent-php\"}}}}\n";
static const char JSON_TRANSACTION[] =
        "{\"transaction\":{\"name\":\"%s\",\"id\":\"%s\",\"trace_id\":\"%s\",\"type\":\"%s\",\"duration\": %.3f, \"timestamp\":%"PRIu64", \"result\": \"0\", \"context\": null, \"spans\": null, \"sampled\": null, \"span_count\": {\"started\": 0}}}\n";
static const char JSON_METRICSET[] =
        "{\"metricset\":{\"samples\":{\"system.cpu.total.norm.pct\":{\"value\":%.2f},\"system.process.cpu.total.norm.pct\":{\"value\":%.2f},\"system.memory.actual.free\":{\"value\":%"PRIu64"},\"system.memory.total\":{\"value\":%"PRIu64"},\"system.process.memory.size\":{\"value\":%"PRIu64"},\"system.process.memory.rss.bytes\":{\"value\":%"PRIu64"}},\"timestamp\":%"PRIu64"}}\n";
static const char JSON_EXCEPTION[] =
        "{\"error\":{\"timestamp\":%"PRIu64",\"id\":\"%s\",\"parent_id\":\"%s\",\"trace_id\":\"%s\",\"exception\":{\"code\":%ld,\"message\":\"%s\",\"type\":\"%s\",\"stacktrace\":[{\"filename\":\"%s\",\"lineno\":%ld}]}}}\n";
static const char JSON_ERROR[] =
        "{\"error\":{\"timestamp\":%"PRIu64",\"id\":\"%s\",\"parent_id\":\"%s\",\"trace_id\":\"%s\",\"exception\":{\"code\": %d,\"message\":\"%s\",\"type\":\"%s\",\"stacktrace\":[{\"filename\":\"%s\",\"lineno\": %d}]},\"log\":{\"level\":\"%s\",\"logger_name\":\"PHP\",\"message\":\"%s\"}}}\n";

// Log response
static size_t log_response( void* ptr, size_t size, size_t nmemb, char* response )
{
    log_debug( "Response body: %s", ptr );
    return size * nmemb;
}

static void elasticApmThrowExceptionHook( zval* exception )
{
    ResultCode resultCode;
    zval * code, *message, *file, *line;
    zval rv;
    zend_class_entry * default_ce;
    zend_string * classname;
    const char* errorId = NULL;
    char* jsonBuffer = NULL;
    static const UInt jsonBufferMaxLength = 10 * 1024; // 10 KB
    uint64_t timestamp;
    GlobalState* const globalState = getGlobalState();

    default_ce = Z_OBJCE_P( exception );

    classname = Z_OBJ_HANDLER_P( exception, get_class_name )( Z_OBJ_P( exception ) );
    code = zend_read_property( default_ce, exception, "code", sizeof( "code" ) - 1, 0, &rv );
    message = zend_read_property( default_ce, exception, "message", sizeof( "message" ) - 1, 0, &rv );
    file = zend_read_property( default_ce, exception, "file", sizeof( "file" ) - 1, 0, &rv );
    line = zend_read_property( default_ce, exception, "line", sizeof( "line" ) - 1, 0, &rv );

    CALL_IF_FAILED_GOTO( genRandomIdHexString( ERROR_ID_SIZE_IN_BYTES, &errorId ) );

    timestamp = getCurrentTimeEpochMicroseconds();

    ALLOC_STRING_IF_FAILED_GOTO( jsonBufferMaxLength, jsonBuffer );
    sprintf( jsonBuffer
             , JSON_EXCEPTION
             , timestamp
             , errorId
             , globalState->currentTransaction->id
             , globalState->currentTransaction->traceId
             , (long)Z_LVAL_P( code )
             , Z_STRVAL_P( message )
             , ZSTR_VAL( classname )
             , Z_STRVAL_P( file )
             , (long)Z_LVAL_P( line ) );

    static const UInt maxGlobalStateExceptionsLength = 100 * 1024; // 100 KB
    if ( globalState->exceptions == NULL ) ALLOC_STRING_IF_FAILED_GOTO( maxGlobalStateExceptionsLength, globalState->exceptions );
    strcpy( globalState->exceptions, jsonBuffer );

    finally:
    EFREE_AND_SET_TO_NULL( jsonBuffer );
    EFREE_AND_SET_TO_NULL( errorId );

    if ( globalState->originalZendThrowExceptionHook != NULL ) globalState->originalZendThrowExceptionHook( exception );

    return;

    failure:
    goto finally;
}

static void elasticApmErrorCallback( int type, const char* error_filename, const uint32_t error_lineno, const char* format, va_list args )
{
    ResultCode resultCode;
    va_list args_copy;
    char* msg;

    va_copy( args_copy, args );
    vspprintf( &msg, 0, format, args_copy );
            va_end( args_copy );

    const char* errorId;
    CALL_IF_FAILED_GOTO( genRandomIdHexString( ERROR_ID_SIZE_IN_BYTES, &errorId ) );

    uint64_t timestamp = getCurrentTimeEpochMicroseconds();

    GlobalState* const globalState = getGlobalState();
    static const UInt jsonBufferMaxLength = 10 * 1024; // 10 KB
    char* jsonBuffer = NULL;
    ALLOC_STRING_IF_FAILED_GOTO( jsonBufferMaxLength, jsonBuffer );
    sprintf( jsonBuffer
             , JSON_ERROR
             , timestamp
             , errorId
             , globalState->currentTransaction->id
             , globalState->currentTransaction->traceId
             , type
             , msg
             , get_php_error_name( type )
             , error_filename
             , error_lineno
             , get_php_error_name( type )
             , msg
    );

    static const UInt maxGlobalStateErrorsLength = 100 * 1024; // 100 KB
    if ( globalState->errors == NULL ) ALLOC_STRING_IF_FAILED_GOTO( maxGlobalStateErrorsLength, globalState->errors );
    strcpy( globalState->errors, jsonBuffer );

    finally:
    EFREE_AND_SET_TO_NULL( jsonBuffer );
    EFREE_AND_SET_TO_NULL( errorId );

    if ( globalState->originalZendErrorCallback != NULL ) globalState->originalZendErrorCallback( type, error_filename, error_lineno, format, args );

    return;

    failure:
    goto finally;
}

ResultCode elasticApmModuleInit( int type, int moduleNumber )
{
    ResultCode resultCode;
    GlobalState* const globalState = getGlobalState();
    const Config* config = NULL;

    LOG_FUNCTION_ENTRY();

    CALL_IF_FAILED_GOTO( initGlobalState( globalState ) );

    registerElasticApmIniEntries( moduleNumber );
    globalState->iniEntriesRegistered = true;
    config = getCurrentConfig();

    // __asm__("int3");
    if ( !config->enable )
    {
        LOG_FUNCTION_EXIT_MSG( "Because extension is not enabled" );
        goto finally;
    }

    if ( strIsNullOrEmtpy( config->service_name ) )
    {
        LOG_MSG( "Because elasticapm.service_name is not set" );
        zend_throw_exception(
                spl_ce_RuntimeException, "You need to specify a service name in elasticapm.service_name", 0 TSRMLS_CC );
    }

    globalState->originalZendErrorCallback = zend_error_cb;
    zend_error_cb = elasticApmErrorCallback;
    globalState->originalZendErrorCallbackSet = true;

    globalState->originalZendThrowExceptionHook = zend_throw_exception_hook;
    zend_throw_exception_hook = elasticApmThrowExceptionHook;
    globalState->originalZendThrowExceptionHookSet = true;

    CURLcode result = curl_global_init( CURL_GLOBAL_ALL );
    if ( result != CURLE_OK )
    {
        resultCode = resultFailure;
        LOG_FUNCTION_EXIT_MSG( "curl_global_init failed" );
        goto finally;
    }
    globalState->curlInited = true;

    resultCode = resultSuccess;
    LOG_FUNCTION_EXIT();

    finally:
    return resultCode;

    failure:
    elasticApmModuleShutdown( type, moduleNumber );
    goto finally;
}

ResultCode elasticApmModuleShutdown( int type, int moduleNumber )
{
    LOG_FUNCTION_ENTRY();

    GlobalState* const globalState = getGlobalState();

    if ( globalState->curlInited )
    {
        curl_global_cleanup();
        globalState->curlInited = false;
    }

    if ( globalState->originalZendThrowExceptionHookSet )
    {
        zend_throw_exception_hook = globalState->originalZendThrowExceptionHook;
        globalState->originalZendThrowExceptionHook = NULL;
        globalState->originalZendThrowExceptionHookSet = false;
    }
    if ( globalState->originalZendErrorCallbackSet )
    {
        zend_error_cb = globalState->originalZendErrorCallback;
        globalState->originalZendErrorCallback = NULL;
        globalState->originalZendErrorCallbackSet = false;
    }

    if ( globalState->iniEntriesRegistered )
    {
        unregisterElasticApmIniEntries( moduleNumber );
        globalState->iniEntriesRegistered = false;
    }

    cleanupGlobalState( getGlobalState() );
    // from this point global state should not be used anymore

    LOG_FUNCTION_EXIT();
    return resultSuccess;
}

ResultCode elasticApmRequestInit()
{
#if defined(ZTS) && defined(COMPILE_DL_ELASTICAPM)
    ZEND_TSRMLS_CACHE_UPDATE();
#endif

    ResultCode resultCode;
    GlobalState* const globalState = getGlobalState();

    LOG_FUNCTION_ENTRY();

    if ( !globalState->config.enable )
    {
        resultCode = resultSuccess;
        LOG_FUNCTION_EXIT_MSG( "Because extension is not enabled" );
        goto finally;
    }

    CALL_IF_FAILED_GOTO( newTransaction( &globalState->currentTransaction ) );

    readSystemMetrics( &globalState->startSystemMetricsReading );

    resultCode = resultSuccess;
    LOG_FUNCTION_EXIT();

    finally:
    return resultCode;

    failure:
    goto finally;
}

static void appendMetadata( const Config* config, char* body )
{
    char* json_metadata = emalloc( sizeof( char ) * 1024 );
    sprintf( json_metadata, JSON_METADATA, getpid(), config->service_name, PHP_ELASTICAPM_VERSION );

    strcat( body, json_metadata );
    efree( json_metadata );
}

#define ELASTICAPM_REQUEST_DATA_FIELD( var ) zval *var; bool var##Found

struct RequestData
{
    ELASTICAPM_REQUEST_DATA_FIELD( uri );
    ELASTICAPM_REQUEST_DATA_FIELD( host );
    ELASTICAPM_REQUEST_DATA_FIELD( clientIp );
//    ELASTICAPM_REQUEST_DATA_FIELD(referer);
//    ELASTICAPM_REQUEST_DATA_FIELD(request_time);
    ELASTICAPM_REQUEST_DATA_FIELD( scriptName );
    ELASTICAPM_REQUEST_DATA_FIELD( httpMethod );
    ELASTICAPM_REQUEST_DATA_FIELD( path );
};
typedef struct RequestData RequestData;

static void getRequestData( RequestData* requestData )
{
    zval * tmp;

#define FETCH_HTTP_GLOBALS( name ) (tmp = &PG(http_globals)[TRACK_VARS_##name])
#define REGISTER_INFO( name, request_data_field, type ) \
        requestData->request_data_field##Found = \
            ((requestData->request_data_field = zend_hash_str_find(Z_ARRVAL_P(tmp), name, sizeof(name) - 1)) \
            && (Z_TYPE_P(requestData->request_data_field) == (type)));

    zend_is_auto_global_str( ZEND_STRL( "_SERVER" ) );
    if ( FETCH_HTTP_GLOBALS( SERVER ) )
    {
        REGISTER_INFO( "REQUEST_URI", uri, IS_STRING )
        REGISTER_INFO( "HTTP_HOST", host, IS_STRING )
//        REGISTER_INFO("HTTP_REFERER", referer, IS_STRING)
//        REGISTER_INFO("REQUEST_TIME", request_time, IS_LONG)
        REGISTER_INFO( "SCRIPT_FILENAME", scriptName, IS_STRING )
        REGISTER_INFO( "REQUEST_METHOD", httpMethod, IS_STRING )
        REGISTER_INFO( "REMOTE_ADDR", clientIp, IS_STRING )
        REGISTER_INFO( "PWD", path, IS_STRING )
    }
}

static void appendTransaction( const Transaction* transaction, const TimePoint* currentTime, char* body )
{
    // Transaction
    char txType[8];
    char* jsonBuffer = NULL;

    RequestData requestData;
    getRequestData( &requestData );

    // if HTTP method exists it is a HTTP request
    char* txName = emalloc( sizeof( char ) * 1024 );
    if ( requestData.httpMethodFound )
    {
        sprintf( txType, "%s", "request" );
        sprintf( txName, "%s %s", Z_STRVAL_P( requestData.httpMethod ), Z_STRVAL_P( requestData.uri ) );
    }
    else
    {
        sprintf( txType, "%s", "script" );
        const char* const txNameSrc = ( requestData.scriptNameFound && ! strIsNullOrEmtpy( Z_STRVAL_P( requestData.scriptName ) ) ) ? Z_STRVAL_P( requestData.scriptName ) : "Unknown";
        sprintf( txName, "%s", txNameSrc );
    }

    jsonBuffer = emalloc( sizeof( char ) * 1024 );
    sprintf( jsonBuffer
             , JSON_TRANSACTION
             , txName
             , transaction->id
             , transaction->traceId
             , txType
             , durationMicrosecondsToMilliseconds( durationMicroseconds( &transaction->startTime, currentTime ) )
             , timePointToEpochMicroseconds( &transaction->startTime )
    );
    strcat( body, jsonBuffer );
    efree( jsonBuffer );
    efree( txName );
}

static void appendMetrics( const SystemMetricsReading* startSystemMetricsReading, const TimePoint* currentTime, char* body )
{
    SystemMetricsReading endSystemMetricsReading;
    readSystemMetrics( &endSystemMetricsReading );
    SystemMetrics system_metrics;
    getSystemMetrics( startSystemMetricsReading, &endSystemMetricsReading, &system_metrics );

    char* json_metricset = emalloc( sizeof( char ) * 1024 );
    sprintf( json_metricset
             , JSON_METRICSET
             , system_metrics.machineCpu          // system.cpu.total.norm.pct
             , system_metrics.processCpu          // system.process.cpu.total.norm.pct
             , system_metrics.machineMemoryFree  // system.memory.actual.free
             , system_metrics.machineMemoryTotal // system.memory.total
             , system_metrics.processMemorySize  // system.process.memory.size
             , system_metrics.processMemoryRss   // system.process.memory.rss.bytes
             , timePointToEpochMicroseconds( currentTime ) );
    strcat( body, json_metricset );
    efree( json_metricset );
}

static void sendPayload( CURL* curl, const Config* config, const char* body )
{
    CURLcode result;
    struct curl_slist* chunk = NULL;
    FILE* logFile = NULL;

    /* Initialize the log file */
    if ( !strIsNullOrEmtpy( config->log ) )
    {
        logFile = fopen( config->log, "a" );
        if ( logFile == NULL )
        {
            // TODO: manage the error
        }
        log_set_fp( logFile );
        log_set_quiet( 1 );
        log_set_level( config->log_level );

        // TODO: check how to set lock and level
        //log_set_lock(1);
    }

    curl_easy_setopt( curl, CURLOPT_POST, 1L );
    curl_easy_setopt( curl, CURLOPT_POSTFIELDS, body );
    curl_easy_setopt( curl, CURLOPT_WRITEFUNCTION, log_response );
    log_debug( "Request body: %s", body );

    // Authorization with secret token if present
    if ( !strIsNullOrEmtpy( config->secret_token ) )
    {
        char* auth = emalloc( sizeof( char ) * 256 );
        sprintf( auth, "Authorization: Bearer %s", config->secret_token );
        chunk = curl_slist_append( chunk, auth );
        efree( auth );
    }
    chunk = curl_slist_append( chunk, "Content-Type: application/x-ndjson" );
    curl_easy_setopt( curl, CURLOPT_HTTPHEADER, chunk );

    // User agent
    char* useragent = emalloc( sizeof( char ) * 100 );
    sprintf( useragent, "elasticapm-php/%s", PHP_ELASTICAPM_VERSION );
    curl_easy_setopt( curl, CURLOPT_USERAGENT, useragent );

    char* url = emalloc( sizeof( char ) * 256 );
    sprintf( url, "%s/intake/v2/events", config->server_url );
    curl_easy_setopt( curl, CURLOPT_URL, url );

    result = curl_easy_perform( curl );
    if ( result != CURLE_OK )
    {
        log_error( "%s %s", config->server_url, curl_easy_strerror( result ) );
    }
    else
    {
        long response_code;
        curl_easy_getinfo( curl, CURLINFO_RESPONSE_CODE, &response_code );
        log_debug( "Response HTTP code: %ld", response_code );
    }

    efree( url );
    efree( useragent );

    fclose( logFile );
}

ResultCode elasticApmRequestShutdown()
{
    ResultCode resultCode;
    CURL* curl = NULL;
    char* body = NULL;
    TimePoint currentTime;
    GlobalState* globalState = getGlobalState();
    const Config* const config = &( globalState->config );

    LOG_FUNCTION_ENTRY();

    if ( !config->enable )
    {
        resultCode = resultSuccess;
        LOG_FUNCTION_EXIT_MSG( "Because extension is not enabled" );
        goto finally;
    }

    getCurrentTime( &currentTime );

    /* get a curl handle */
    curl = curl_easy_init();
    if ( curl == NULL )
    {
        resultCode = resultFailure;
        LOG_FUNCTION_EXIT_MSG( "Because curl_easy_init() returned NULL" );
        goto failure;
    }

    // body
    body = emalloc( sizeof( char ) * 102400 ); // max size 100 Kb
    body[ 0 ] = '\0';

    appendMetadata( config, body );

    appendTransaction( globalState->currentTransaction, &currentTime, body );

    appendMetrics( &globalState->startSystemMetricsReading, &currentTime, body );

    if ( !strIsNullOrEmtpy( globalState->errors ) ) strcat( body, globalState->errors );
    if ( !strIsNullOrEmtpy( globalState->exceptions ) ) strcat( body, globalState->exceptions );

    sendPayload( curl, config, body );

    resultCode = resultSuccess;
    LOG_FUNCTION_EXIT();

    finally:
    if ( curl != NULL ) curl_easy_cleanup( curl );
    EFREE_AND_SET_TO_NULL( body );
    deleteTransactionAndSetToNull( &globalState->currentTransaction );
    return resultCode;

    failure:
    goto finally;
}
