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
#include <Zend/zend_API.h>
#include <Zend/zend_compile.h>
#include <Zend/zend_exceptions.h>

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
#include "elasticapm_assert.h"

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
static size_t logResponse( void* data, size_t unusedSizeParam, size_t dataSize, void* unusedUserDataParam )
{
    // https://curl.haxx.se/libcurl/c/CURLOPT_WRITEFUNCTION.html
    // size (unusedSizeParam) is always 1
    UNUSED_PARAMETER( unusedSizeParam );
    UNUSED_PARAMETER( unusedUserDataParam );
    log_debug( "APM Server's response body [length: %"PRIu64"]: %.*s", (UInt64)dataSize, dataSize, (const char*)data );
    return dataSize;
}

static ResultCode ensureAllocatedAndAppend( MutableString* pBuffer, size_t maxLength, String strToAppend )
{
    ASSERT_VALID_PTR( pBuffer );

    ResultCode resultCode;
    size_t bufferUsedLength;
    MutableString buffer = *pBuffer;

    if ( buffer == NULL )
    {
        ALLOC_STRING_IF_FAILED_GOTO( maxLength, buffer );
        bufferUsedLength = 0;
    }
    else
    {
        bufferUsedLength = strnlen( buffer, maxLength );
    }

    strncat( buffer + bufferUsedLength, strToAppend, maxLength - bufferUsedLength );

    resultCode = resultSuccess;
    if ( *pBuffer == NULL ) *pBuffer = buffer;

    finally:
    return resultCode;

    failure:
    if ( *pBuffer == NULL ) EFREE_AND_SET_TO_NULL( buffer );
    goto finally;
}

static void elasticApmThrowExceptionHook( zval* exception )
{
    ResultCode resultCode;
    zval* code;
    zval* message;
    zval* file;
    zval* line;
    zval rv;
    zend_class_entry* default_ce;
    zend_string* className;
    const char* errorId = NULL;
    char* jsonBuffer = NULL;
    static const UInt jsonBufferMaxLength = 10 * 1024; // 10 KB
    uint64_t timestamp;
    GlobalState* const globalState = getGlobalState();
    Transaction* const currentTransaction = globalState->currentTransaction;

    if ( currentTransaction == NULL )
    {
        resultCode = resultSuccess;
        LOG_FUNCTION_EXIT_MSG( "Because there is no current transaction" );
        goto finally;
    }

    default_ce = Z_OBJCE_P( exception );

    className = Z_OBJ_HANDLER_P( exception, get_class_name )( Z_OBJ_P( exception ) );
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
             , currentTransaction->id
             , currentTransaction->traceId
             , (long)Z_LVAL_P( code )
             , Z_STRVAL_P( message )
             , ZSTR_VAL( className )
             , Z_STRVAL_P( file )
             , (long)Z_LVAL_P( line ) );

    CALL_IF_FAILED_GOTO( ensureAllocatedAndAppend( &currentTransaction->exceptions, 100 * 1024, jsonBuffer ) );

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
    char* msg = NULL;
    const char* errorId = NULL;
    GlobalState* const globalState = getGlobalState();
    Transaction* const currentTransaction = globalState->currentTransaction;
    static const UInt jsonBufferMaxLength = 10 * 1024; // 10 KB
    char* jsonBuffer = NULL;
    const uint64_t timestamp = getCurrentTimeEpochMicroseconds();

    if ( currentTransaction == NULL )
    {
        resultCode = resultSuccess;
        LOG_FUNCTION_EXIT_MSG( "Because there is no current transaction" );
        goto finally;
    }

    va_copy( args_copy, args );
    vspprintf( &msg, 0, format, args_copy );
    va_end( args_copy );

    CALL_IF_FAILED_GOTO( genRandomIdHexString( ERROR_ID_SIZE_IN_BYTES, &errorId ) );

    ALLOC_STRING_IF_FAILED_GOTO( jsonBufferMaxLength, jsonBuffer );
    sprintf( jsonBuffer
             , JSON_ERROR
             , timestamp
             , errorId
             , currentTransaction->id
             , currentTransaction->traceId
             , type
             , msg
             , get_php_error_name( type )
             , error_filename
             , error_lineno
             , get_php_error_name( type )
             , msg
    );

    CALL_IF_FAILED_GOTO( ensureAllocatedAndAppend( &currentTransaction->errors, 100 * 1024, jsonBuffer ) );

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
    if ( !config->enabled )
    {
        LOG_FUNCTION_EXIT_MSG( "Because extension is not enabled" );
        goto finally;
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
    UNUSED_PARAMETER( type );
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

    if ( !globalState->config.enabled )
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

static const size_t serializedEventsMaxLength = 1000 * 1000; // one million

static void appendSerializedEvent( const char* serializedEvent, char* serializedEventsBuffer )
{
    size_t bufferUsedLength = strnlen( serializedEventsBuffer, serializedEventsMaxLength );
    strncat( serializedEventsBuffer + bufferUsedLength, serializedEvent, serializedEventsMaxLength - bufferUsedLength );
}

static void appendMetadata( const Config* config, char* serializedEventsBuffer )
{
    char* jsonBuffer = emalloc( sizeof( char ) * 1024 );
    sprintf( jsonBuffer, JSON_METADATA, getpid(), config->serviceName, PHP_ELASTICAPM_VERSION );
    appendSerializedEvent( jsonBuffer, serializedEventsBuffer );
    EFREE_AND_SET_TO_NULL( jsonBuffer );
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

static void appendTransaction( const Transaction* transaction, const TimePoint* currentTime, char* serializedEventsBuffer )
{
    // Transaction
    char txType[8];
    char* jsonBuffer = NULL;
    char* txName = NULL;

    RequestData requestData;
    getRequestData( &requestData );

    // if HTTP method exists it is a HTTP request
    txName = emalloc( sizeof( char ) * 1024 );
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
#ifdef PHP_WIN32
        strReplaceChar( txName, '\\', '/' );
#endif
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
    appendSerializedEvent( jsonBuffer, serializedEventsBuffer );
    EFREE_AND_SET_TO_NULL( jsonBuffer );
    EFREE_AND_SET_TO_NULL( txName );
}

static void appendMetrics( const SystemMetricsReading* startSystemMetricsReading, const TimePoint* currentTime, char* serializedEventsBuffer )
{
    SystemMetricsReading endSystemMetricsReading;
    readSystemMetrics( &endSystemMetricsReading );
    SystemMetrics system_metrics;
    getSystemMetrics( startSystemMetricsReading, &endSystemMetricsReading, &system_metrics );

    char* jsonBuffer = emalloc( sizeof( char ) * 1024 );
    sprintf( jsonBuffer
             , JSON_METRICSET
             , system_metrics.machineCpu          // system.cpu.total.norm.pct
             , system_metrics.processCpu          // system.process.cpu.total.norm.pct
             , system_metrics.machineMemoryFree  // system.memory.actual.free
             , system_metrics.machineMemoryTotal // system.memory.total
             , system_metrics.processMemorySize  // system.process.memory.size
             , system_metrics.processMemoryRss   // system.process.memory.rss.bytes
             , timePointToEpochMicroseconds( currentTime ) );
    appendSerializedEvent( jsonBuffer, serializedEventsBuffer );
    EFREE_AND_SET_TO_NULL( jsonBuffer );
}

static void sendEventsToApmServer( CURL* curl, const Config* config, const char* serializedEventsBuffer )
{
    CURLcode result;
    char* auth = NULL;
    char* userAgent = NULL;
    char* url = NULL;
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
        log_set_level( config->logLevel );

        // TODO: check how to set lock and level
        //log_set_lock(1);
    }

    curl_easy_setopt( curl, CURLOPT_POST, 1L );
    curl_easy_setopt( curl, CURLOPT_POSTFIELDS, serializedEventsBuffer );
    curl_easy_setopt( curl, CURLOPT_WRITEFUNCTION, logResponse );
    log_debug( "Request serializedEventsBuffer [length: %"PRIu64"]: %s", (UInt64)strnlen( serializedEventsBuffer, serializedEventsMaxLength ), serializedEventsBuffer );

    // Authorization with secret token if present
    if ( !strIsNullOrEmtpy( config->secretToken ) )
    {
        auth = emalloc( sizeof( char ) * 256 );
        sprintf( auth, "Authorization: Bearer %s", config->secretToken );
        chunk = curl_slist_append( chunk, auth );
    }
    chunk = curl_slist_append( chunk, "Content-Type: application/x-ndjson" );
    curl_easy_setopt( curl, CURLOPT_HTTPHEADER, chunk );

    // User agent
    userAgent = emalloc( sizeof( char ) * 100 );
    sprintf( userAgent, "elasticapm-php/%s", PHP_ELASTICAPM_VERSION );
    curl_easy_setopt( curl, CURLOPT_USERAGENT, userAgent );

    url = emalloc( sizeof( char ) * 256 );
    sprintf( url, "%s/intake/v2/events", config->serverUrl );
    curl_easy_setopt( curl, CURLOPT_URL, url );

    result = curl_easy_perform( curl );
    if ( result != CURLE_OK )
    {
        log_error( "%s %s", config->serverUrl, curl_easy_strerror( result ) );
    }
    else
    {
        long response_code;
        curl_easy_getinfo( curl, CURLINFO_RESPONSE_CODE, &response_code );
        log_debug( "Response HTTP code: %ld", response_code );
    }

    EFREE_AND_SET_TO_NULL( url );
    EFREE_AND_SET_TO_NULL( userAgent );
    EFREE_AND_SET_TO_NULL( auth );

    if ( logFile != NULL ) fclose( logFile );
}

ResultCode elasticApmRequestShutdown()
{
    ResultCode resultCode;
    CURL* curl = NULL;
    char* serializedEventsBuffer = NULL;
    TimePoint currentTime;
    GlobalState* const globalState = getGlobalState();
    const Config* const config = &( globalState->config );
    Transaction* const currentTransaction = globalState->currentTransaction;

    LOG_FUNCTION_ENTRY();

    if ( currentTransaction == NULL )
    {
        resultCode = resultSuccess;
        LOG_FUNCTION_EXIT_MSG( "Because there is no current transaction" );
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

    ALLOC_STRING_IF_FAILED_GOTO( serializedEventsMaxLength, serializedEventsBuffer ); // max length 10^6
    serializedEventsBuffer[ 0 ] = '\0';

    appendMetadata( config, serializedEventsBuffer );

    appendTransaction( currentTransaction, &currentTime, serializedEventsBuffer );

    appendMetrics( &globalState->startSystemMetricsReading, &currentTime, serializedEventsBuffer );

//    if ( !strIsNullOrEmtpy( currentTransaction->errors ) ) appendSerializedEvent( currentTransaction->errors, serializedEventsBuffer );
    if ( !strIsNullOrEmtpy( currentTransaction->exceptions ) ) appendSerializedEvent( currentTransaction->exceptions, serializedEventsBuffer );

    sendEventsToApmServer( curl, config, serializedEventsBuffer );

    resultCode = resultSuccess;
    LOG_FUNCTION_EXIT();

    finally:
    if ( curl != NULL ) curl_easy_cleanup( curl );
    EFREE_AND_SET_TO_NULL( serializedEventsBuffer );
    deleteTransactionAndSetToNull( &globalState->currentTransaction );
    return resultCode;

    failure:
    goto finally;
}
