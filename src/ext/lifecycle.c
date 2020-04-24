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
#include <inttypes.h> // PRIu64
#include <stdbool.h>
#include <php.h>
#include <zend_compile.h>
#include <zend_exceptions.h>
#if defined(PHP_WIN32) && !defined(CURL_STATICLIB)
#   define CURL_STATICLIB
#endif
#include <curl/curl.h>
#include "php_elasticapm.h"
#include "log.h"
#include "SystemMetrics.h"
#include "php_error.h"
#include "util_for_php.h"
#include "elasticapm_assert.h"
#include "MemoryTracker.h"
#include "supportability.h"

static const char JSON_METADATA[] =
        "{\"metadata\":{\"process\":{\"pid\":%d},\"service\":{\"name\":\"%s\",\"language\":{\"name\":\"PHP\"},\"agent\":{\"version\":\"%s\",\"name\":\"PHP\"}}}}\n";
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
    ELASTICAPM_UNUSED( unusedSizeParam );
    ELASTICAPM_UNUSED( unusedUserDataParam );

    ELASTICAPM_LOG_DEBUG( "APM Server's response body [length: %"PRIu64"]: %.*s", (UInt64)dataSize, (int)dataSize, (const char*)data );
    return dataSize;
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
    char errorId[ errorIdAsHexStringBufferSize ];
    uint64_t timestamp;
    Tracer* const tracer = getGlobalTracer();
    Transaction* const currentTransaction = tracer->currentTransaction;

    if ( currentTransaction == NULL )
    {
        resultCode = resultSuccess;
        ELASTICAPM_LOG_TRACE_FUNCTION_EXIT_MSG( "Because there is no current transaction" );
        goto finally;
    }

    default_ce = Z_OBJCE_P( exception );

    className = Z_OBJ_HANDLER_P( exception, get_class_name )( Z_OBJ_P( exception ) );
    code = zend_read_property( default_ce, exception, "code", sizeof( "code" ) - 1, 0, &rv );
    message = zend_read_property( default_ce, exception, "message", sizeof( "message" ) - 1, 0, &rv );
    file = zend_read_property( default_ce, exception, "file", sizeof( "file" ) - 1, 0, &rv );
    line = zend_read_property( default_ce, exception, "line", sizeof( "line" ) - 1, 0, &rv );

    ELASTICAPM_GEN_RANDOM_ID_AS_HEX_STRING( errorIdSizeInBytes, errorId );

    timestamp = getCurrentTimeEpochMicroseconds();

    if ( currentTransaction->exceptionsBuffer == NULL )
    {
        ELASTICAPM_EMALLOC_STRING_IF_FAILED_GOTO( exceptionsBufferSize, currentTransaction->exceptionsBuffer );
        currentTransaction->exceptionsTextOutputStream = makeTextOutputStream( currentTransaction->exceptionsBuffer, exceptionsBufferSize );
        currentTransaction->exceptionsTextOutputStream.autoTermZero = false;
    }

    streamPrintf(
            &currentTransaction->exceptionsTextOutputStream,
            JSON_EXCEPTION,
            timestamp,
            errorId,
            currentTransaction->id,
            currentTransaction->traceId,
            (long)Z_LVAL_P( code ),
            Z_STRVAL_P( message ),
            ZSTR_VAL( className ),
            Z_STRVAL_P( file ),
            (long)Z_LVAL_P( line ) );

    resultCode = resultSuccess;

    finally:
    if ( tracer->originalZendThrowExceptionHook != NULL )
        tracer->originalZendThrowExceptionHook( exception );

    ELASTICAPM_UNUSED( resultCode );
    return;

    failure:
    goto finally;
}

static void elasticApmErrorCallback( int type, String error_filename, uint32_t error_lineno, String format, va_list args )
{
    ResultCode resultCode;
    va_list args_copy;
    char* msgBuffer = NULL;
    static const UInt msgBufferSize = 100 * 1024;
    char errorId[ errorIdAsHexStringBufferSize ];
    Tracer* const tracer = getGlobalTracer();
    Transaction* const currentTransaction = tracer->currentTransaction;
    const uint64_t timestamp = getCurrentTimeEpochMicroseconds();

    if ( currentTransaction == NULL )
    {
        resultCode = resultSuccess;
        ELASTICAPM_LOG_TRACE_FUNCTION_EXIT_MSG( "Because there is no current transaction" );
        goto finally;
    }

    ELASTICAPM_EMALLOC_STRING_IF_FAILED_GOTO( msgBufferSize, msgBuffer );
    va_copy( args_copy, args );
    vsnprintf( msgBuffer, msgBufferSize, format, args_copy );
    va_end( args_copy );

    ELASTICAPM_GEN_RANDOM_ID_AS_HEX_STRING( errorIdSizeInBytes, errorId );

    if ( currentTransaction->errorsBuffer == NULL )
    {
        ELASTICAPM_EMALLOC_STRING_IF_FAILED_GOTO( errorsBufferSize, currentTransaction->errorsBuffer );
        currentTransaction->errorsTextOutputStream = makeTextOutputStream( currentTransaction->errorsBuffer, errorsBufferSize );
        currentTransaction->errorsTextOutputStream.autoTermZero = false;
    }

    #ifdef PHP_WIN32
    const char* freeSpaceBeginBeforeWrite = textOutputStreamGetFreeSpaceBegin( &currentTransaction->errorsTextOutputStream );
    #endif
    streamPrintf(
            &currentTransaction->errorsTextOutputStream,
             JSON_ERROR,
             timestamp,
             errorId,
             currentTransaction->id,
             currentTransaction->traceId,
             type,
             msgBuffer,
             get_php_error_name( type ),
             error_filename,
             error_lineno,
             get_php_error_name( type ),
             msgBuffer );

    #ifdef PHP_WIN32
    const char* freeSpaceBeginAftereWrite = textOutputStreamGetFreeSpaceBegin( &currentTransaction->errorsTextOutputStream );
    replaceCharInStringView( makeStringView( freeSpaceBeginBeforeWrite, freeSpaceBeginAftereWrite - freeSpaceBeginBeforeWrite ), '\\', '/' );
    #endif

    resultCode = resultSuccess;

    finally:
    ELASTICAPM_EFREE_STRING_AND_SET_TO_NULL( msgBufferSize, msgBuffer );

    if ( tracer->originalZendErrorCallback != NULL )
        tracer->originalZendErrorCallback( type, error_filename, error_lineno, format, args );

    ELASTICAPM_UNUSED( resultCode );
    return;

    failure:
    goto finally;
}

static
String buildSupportabilityInfo( size_t supportInfoBufferSize, char* supportInfoBuffer)
{
    TextOutputStream txtOutStream = makeTextOutputStream( supportInfoBuffer, supportInfoBufferSize );
    TextOutputStreamState txtOutStreamStateOnEntryStart;
    if ( ! textOutputStreamStartEntry( &txtOutStream, &txtOutStreamStateOnEntryStart ) )
        return ELASTICAPM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER;

    StructuredTextToOutputStreamPrinter structTxtToOutStreamPrinter;
    initStructuredTextToOutputStreamPrinter(
            /* in */ &txtOutStream
            , /* prefix */ ELASTICAPM_STRING_LITERAL_TO_VIEW( "" )
            , /* out */ &structTxtToOutStreamPrinter );

    printSupportabilityInfo( (StructuredTextPrinter*) &structTxtToOutStreamPrinter );

    return textOutputStreamEndEntry( &txtOutStreamStateOnEntryStart, &txtOutStream );
}

void logSupportabilityInfo( LogLevel logLevel )
{
    ResultCode resultCode;
    enum { supportInfoBufferSize = 100 * 1000 + 1 };
    char* supportInfoBuffer = NULL;

    ELASTICAPM_PEMALLOC_STRING_IF_FAILED_GOTO( supportInfoBufferSize, supportInfoBuffer );
    String supportabilityInfo = buildSupportabilityInfo( supportInfoBufferSize, supportInfoBuffer );

    ELASTICAPM_LOG_WITH_LEVEL( logLevel, "Supportability info:\n%s", supportabilityInfo );

    // resultCode = resultSuccess;

    finally:
    ELASTICAPM_PEFREE_STRING_AND_SET_TO_NULL( supportInfoBufferSize, supportInfoBuffer );
    return;

    failure:
    goto finally;
}

ResultCode elasticApmModuleInit( int type, int moduleNumber )
{
    ResultCode resultCode;
    Tracer* const tracer = getGlobalTracer();
    const ConfigSnapshot* config = NULL;

    ELASTICAPM_CALL_IF_FAILED_GOTO( constructTracer( tracer ) );

    ELASTICAPM_LOG_DEBUG_FUNCTION_ENTRY();

    registerElasticApmIniEntries( moduleNumber, &tracer->iniEntriesRegistrationState );

    ELASTICAPM_CALL_IF_FAILED_GOTO( ensureAllComponentsHaveLatestConfig( tracer ) );
    logSupportabilityInfo( logLevel_debug );
    config = getTracerCurrentConfigSnapshot( tracer );

    if ( ! config->enabled )
    {
        ELASTICAPM_LOG_DEBUG_FUNCTION_EXIT_MSG( "Because extension is not enabled" );
        goto finally;
    }

    tracer->originalZendErrorCallback = zend_error_cb;
    zend_error_cb = elasticApmErrorCallback;
    tracer->originalZendErrorCallbackSet = true;

    tracer->originalZendThrowExceptionHook = zend_throw_exception_hook;
    zend_throw_exception_hook = elasticApmThrowExceptionHook;
    tracer->originalZendThrowExceptionHookSet = true;

    CURLcode result = curl_global_init( CURL_GLOBAL_ALL );
    if ( result != CURLE_OK )
    {
        resultCode = resultFailure;
        ELASTICAPM_LOG_TRACE_FUNCTION_EXIT_MSG( "curl_global_init failed" );
        goto finally;
    }
    tracer->curlInited = true;

    resultCode = resultSuccess;
    ELASTICAPM_LOG_DEBUG_FUNCTION_EXIT();

    finally:
    return resultCode;

    failure:
    elasticApmModuleShutdown( type, moduleNumber );
    goto finally;
}

ResultCode elasticApmModuleShutdown( int type, int moduleNumber )
{
    ELASTICAPM_UNUSED( type );

    ResultCode resultCode;

    ELASTICAPM_LOG_DEBUG_FUNCTION_ENTRY();

    Tracer* const tracer = getGlobalTracer();

    if ( !tracer->isInited )
    {
        resultCode = resultFailure;
        ELASTICAPM_LOG_TRACE_FUNCTION_EXIT_MSG( "Extension is not initialized" );
        goto finally;
    }

    if ( tracer->curlInited )
    {
        curl_global_cleanup();
        tracer->curlInited = false;
    }

    if ( tracer->originalZendThrowExceptionHookSet )
    {
        zend_throw_exception_hook = tracer->originalZendThrowExceptionHook;
        tracer->originalZendThrowExceptionHook = NULL;
        tracer->originalZendThrowExceptionHookSet = false;
    }
    if ( tracer->originalZendErrorCallbackSet )
    {
        zend_error_cb = tracer->originalZendErrorCallback;
        tracer->originalZendErrorCallback = NULL;
        tracer->originalZendErrorCallbackSet = false;
    }

    unregisterElasticApmIniEntries( moduleNumber, &tracer->iniEntriesRegistrationState );

    resultCode = resultSuccess;
    ELASTICAPM_LOG_DEBUG_FUNCTION_EXIT();

    finally:
    destructTracer( tracer );
    return resultCode;
}

static
ResultCode bootstrapPhpPart( const ConfigSnapshot* config )
{
    char txtOutStreamBuf[ ELASTICAPM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTICAPM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    ELASTICAPM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "config->bootstrapPhpPartFile: %s"
                                             , streamUserString( config->bootstrapPhpPartFile, &txtOutStream ) );

    ResultCode resultCode;

    if ( config->bootstrapPhpPartFile == NULL )
    {
        // For now we don't consider option not being set as a failure
        GetConfigManagerOptionMetadataResult getMetaRes;
        getConfigManagerOptionMetadata( getGlobalTracer()->configManager, optionId_bootstrapPhpPartFile, &getMetaRes );
        ELASTICAPM_LOG_INFO( "Configuration option `%s' is not set", getMetaRes.optName );
        resultCode = resultSuccess;
        goto finally;
    }

    ELASTICAPM_CALL_IF_FAILED_GOTO( loadPhpFile( config->bootstrapPhpPartFile ) );
    ELASTICAPM_CALL_IF_FAILED_GOTO( callPhpFunction( ELASTICAPM_STRING_LITERAL_TO_VIEW( "\\ElasticApm\\Impl\\bootstrapPhpPart" ), logLevel_debug ) );

    resultCode = resultSuccess;

    finally:
    ELASTICAPM_LOG_DEBUG_FUNCTION_EXIT_MSG( "resultCode: %s (%d)", resultCodeToString( resultCode ), resultCode );
    return resultCode;

    failure:
    goto finally;

}

ResultCode elasticApmRequestInit()
{
#if defined(ZTS) && defined(COMPILE_DL_ELASTICAPM)
    ZEND_TSRMLS_CACHE_UPDATE();
#endif

    ResultCode resultCode;
    Tracer* const tracer = getGlobalTracer();

    if ( isMemoryTrackingEnabled( &tracer->memTracker ) ) memoryTrackerRequestInit( &tracer->memTracker );

    ELASTICAPM_LOG_TRACE_FUNCTION_ENTRY();

    if ( !tracer->isInited )
    {
        resultCode = resultFailure;
        ELASTICAPM_LOG_TRACE_FUNCTION_EXIT_MSG( "Extension is not initialized" );
        goto finally;
    }

    if ( !getTracerCurrentConfigSnapshot( tracer )->enabled )
    {
        resultCode = resultSuccess;
        ELASTICAPM_LOG_TRACE_FUNCTION_EXIT_MSG( "Because extension is not enabled" );
        goto finally;
    }

    ELASTICAPM_CALL_IF_FAILED_GOTO( ensureAllComponentsHaveLatestConfig( tracer ) );
    logSupportabilityInfo( logLevel_trace );

    const ConfigSnapshot* config = getTracerCurrentConfigSnapshot( tracer );

    ELASTICAPM_CALL_IF_FAILED_GOTO( bootstrapPhpPart( config ) );

    ELASTICAPM_CALL_IF_FAILED_GOTO( newTransaction( &tracer->currentTransaction ) );

    readSystemMetrics( &tracer->startSystemMetricsReading );

    resultCode = resultSuccess;
    ELASTICAPM_LOG_TRACE_FUNCTION_EXIT();

    finally:
    return resultCode;

    failure:
    goto finally;
}

static
void appendMetadata( const ConfigSnapshot* config, TextOutputStream* serializedEventsTxtOutStream )
{
    streamPrintf( serializedEventsTxtOutStream, JSON_METADATA, getpid(), config->serviceName, PHP_ELASTICAPM_VERSION );
}

struct RequestData
{
    String clientAddress;
    String httpHost;
    String httpMethod;
    String scriptFilePath;
    String urlPath;
};
typedef struct RequestData RequestData;

static ResultCode getRequestDataStringField( const zend_array* serverHttpGlobals, StringView key, String* pField )
{
    ELASTICAPM_LOG_TRACE_FUNCTION_ENTRY_MSG( "key [length: %"PRIu64"]: `%.*s'", (UInt64) key.length, (int) key.length, key.begin );

    ResultCode resultCode;

    ELASTICAPM_ASSERT_VALID_PTR( pField );

    *pField = NULL;

    const zval* fieldZval = findInZarrayByStringKey( serverHttpGlobals, key );
    if ( fieldZval == NULL )
    {
        resultCode = resultSuccess;
        ELASTICAPM_LOG_TRACE_FUNCTION_EXIT_MSG( "array does not contain the key - setting pField to NULL" );
        goto finally;
    }

    if ( Z_TYPE_P( fieldZval ) != IS_STRING )
    {
        resultCode = resultSuccess;
        ELASTICAPM_LOG_TRACE_FUNCTION_EXIT_MSG( "array contains the key but the value's type is not string - setting pField to NULL" );
        goto failure;
    }

    *pField = Z_STRVAL_P( fieldZval );

    resultCode = resultSuccess;
    ELASTICAPM_LOG_TRACE_FUNCTION_EXIT_MSG( "Result: `%s'", *pField );

    finally:
    return resultCode;

    failure:
    goto finally;

}

static ResultCode getRequestData( RequestData* requestData )
{
    ResultCode resultCode;
    const zval* serverHttpGlobalsZval = NULL;
    zend_array* serverHttpGlobals = NULL;

    ELASTICAPM_LOG_TRACE_FUNCTION_ENTRY();

    memset( requestData, 0, sizeof( *requestData ) );

    if ( ! zend_is_auto_global_str( ZEND_STRL( "_SERVER" ) ) )
    {
        resultCode = resultFailure;
        ELASTICAPM_LOG_TRACE_FUNCTION_EXIT_MSG( "zend_is_auto_global_str( ZEND_STRL( \"_SERVER\" ) ) failed" );
        goto failure;
    }

    serverHttpGlobalsZval = &PG( http_globals )[ TRACK_VARS_SERVER ];
    if ( serverHttpGlobalsZval == NULL )
    {
        resultCode = resultFailure;
        ELASTICAPM_LOG_TRACE_FUNCTION_EXIT_MSG( "serverHttpGlobalsZval is NULL" );
        goto failure;
    }

    if ( ! isZarray( serverHttpGlobalsZval ) )
    {
        resultCode = resultFailure;
        ELASTICAPM_LOG_TRACE_FUNCTION_EXIT_MSG( "serverHttpGlobalsZval is not array" );
        goto failure;
    }

    serverHttpGlobals = Z_ARRVAL_P( serverHttpGlobalsZval );
    if ( serverHttpGlobals == NULL )
    {
        resultCode = resultFailure;
        ELASTICAPM_LOG_TRACE_FUNCTION_EXIT_MSG( "serverHttpGlobals is NULL" );
        goto failure;
    }

    // https://www.php.net/manual/en/reserved.variables.server.php
    ELASTICAPM_CALL_IF_FAILED_GOTO( getRequestDataStringField( serverHttpGlobals, ELASTICAPM_STRING_LITERAL_TO_VIEW( "REMOTE_ADDR" ), &requestData->clientAddress ) );
    ELASTICAPM_CALL_IF_FAILED_GOTO( getRequestDataStringField( serverHttpGlobals, ELASTICAPM_STRING_LITERAL_TO_VIEW( "HTTP_HOST" ), &requestData->httpHost ) );
    ELASTICAPM_CALL_IF_FAILED_GOTO( getRequestDataStringField( serverHttpGlobals, ELASTICAPM_STRING_LITERAL_TO_VIEW( "REQUEST_METHOD" ), &requestData->httpMethod ) );
    ELASTICAPM_CALL_IF_FAILED_GOTO( getRequestDataStringField( serverHttpGlobals, ELASTICAPM_STRING_LITERAL_TO_VIEW( "SCRIPT_FILENAME" ), &requestData->scriptFilePath ) );
    ELASTICAPM_CALL_IF_FAILED_GOTO( getRequestDataStringField( serverHttpGlobals, ELASTICAPM_STRING_LITERAL_TO_VIEW( "REQUEST_URI" ), &requestData->urlPath ) );

    resultCode = resultSuccess;
    ELASTICAPM_LOG_TRACE_FUNCTION_EXIT();

    finally:
    return resultCode;

    failure:
    goto finally;
}

static ResultCode appendTransaction( const Transaction* transaction, const TimePoint* currentTime, TextOutputStream* serializedEventsTxtOutStream )
{
    ResultCode resultCode;
    const char* txType = NULL;
    enum { txNameBufferSize = 1024 + 1 };
    char txNameBuffer[ txNameBufferSize ];
    RequestData requestData;

    ELASTICAPM_LOG_TRACE_FUNCTION_ENTRY();

    ELASTICAPM_CALL_IF_FAILED_GOTO( getRequestData( &requestData ) );

    TextOutputStream txNameOutStream = makeTextOutputStream( txNameBuffer, txNameBufferSize );

    // if HTTP method and URL exist then it is a HTTP request
    if ( isNullOrEmtpyString( requestData.httpMethod ) || isNullOrEmtpyString( requestData.urlPath ) )
    {
        txType = "script";
        streamPrintf( &txNameOutStream, "%s", isNullOrEmtpyString( requestData.scriptFilePath ) ? "Unknown" : requestData.scriptFilePath );
        #ifdef PHP_WIN32
        replaceCharInString( txNameBuffer, '\\', '/' );
        #endif
    }
    else
    {
        txType = "request";
        streamPrintf( &txNameOutStream, "%s %s", requestData.httpMethod, requestData.urlPath );
    }

    streamPrintf(
            serializedEventsTxtOutStream,
            JSON_TRANSACTION,
            txNameBuffer,
            transaction->id,
            transaction->traceId,
            txType,
            durationMicrosecondsToMilliseconds( durationMicroseconds( &transaction->startTime, currentTime ) ),
            timePointToEpochMicroseconds( &transaction->startTime ) );

    resultCode = resultSuccess;
    ELASTICAPM_LOG_TRACE_FUNCTION_EXIT();

    finally:
    return resultCode;

    failure:
    goto finally;
}

static
void appendMetrics( const SystemMetricsReading* startSystemMetricsReading, const TimePoint* currentTime, TextOutputStream* serializedEventsTxtOutStream )
{
    SystemMetricsReading endSystemMetricsReading;
    readSystemMetrics( &endSystemMetricsReading );
    SystemMetrics system_metrics;
    getSystemMetrics( startSystemMetricsReading, &endSystemMetricsReading, &system_metrics );

    streamPrintf(
            serializedEventsTxtOutStream,
             JSON_METRICSET,
             system_metrics.machineCpu,          // system.cpu.total.norm.pct
             system_metrics.processCpu,          // system.process.cpu.total.norm.pct
             system_metrics.machineMemoryFree,  // system.memory.actual.free
             system_metrics.machineMemoryTotal, // system.memory.total
             system_metrics.processMemorySize,  // system.process.memory.size
             system_metrics.processMemoryRss,   // system.process.memory.rss.bytes
             timePointToEpochMicroseconds( currentTime ) );
}

static
void sendEventsToApmServer( CURL* curl, const ConfigSnapshot* config, String serializedEventsBuffer )
{
    ELASTICAPM_LOG_DEBUG( "Sending events to APM Server... serializedEventsBuffer [length: %"PRIu64"]:\n%s",
            (UInt64) strlen( serializedEventsBuffer ), serializedEventsBuffer );

    CURLcode result;
    enum { authBufferSize = 256 };
    char auth[ authBufferSize ];
    enum { userAgentBufferSize = 100 };
    char userAgent[ userAgentBufferSize ];
    enum { urlBufferSize = 256 };
    char url[ urlBufferSize ];
    struct curl_slist* chunk = NULL;
    int snprintfRetVal;

    curl_easy_setopt( curl, CURLOPT_POST, 1L );
    curl_easy_setopt( curl, CURLOPT_POSTFIELDS, serializedEventsBuffer );
    curl_easy_setopt( curl, CURLOPT_WRITEFUNCTION, logResponse );
    curl_easy_setopt( curl, CURLOPT_CONNECTTIMEOUT_MS, (long)durationToMilliseconds( config->serverConnectTimeout ) );

    // Authorization with secret token if present
    if ( !isNullOrEmtpyString( config->secretToken ) )
    {
        snprintfRetVal = snprintf( auth, authBufferSize, "Authorization: Bearer %s", config->secretToken );
        if ( snprintfRetVal < 0 || snprintfRetVal >= authBufferSize )
        {
            ELASTICAPM_LOG_ERROR( "Failed to build Authorization header. snprintfRetVal: %d", snprintfRetVal );
            return;
        }
        chunk = curl_slist_append( chunk, auth );
    }
    chunk = curl_slist_append( chunk, "Content-Type: application/x-ndjson" );
    curl_easy_setopt( curl, CURLOPT_HTTPHEADER, chunk );

    // User agent
    snprintfRetVal = snprintf( userAgent, userAgentBufferSize, "elasticapm-php/%s", PHP_ELASTICAPM_VERSION );
    if ( snprintfRetVal < 0 || snprintfRetVal >= authBufferSize )
    {
        ELASTICAPM_LOG_ERROR( "Failed to build User-Agent header. snprintfRetVal: %d", snprintfRetVal );
        return;
    }
    curl_easy_setopt( curl, CURLOPT_USERAGENT, userAgent );

    snprintfRetVal = snprintf( url, urlBufferSize, "%s/intake/v2/events", config->serverUrl );
    if ( snprintfRetVal < 0 || snprintfRetVal >= authBufferSize )
    {
        ELASTICAPM_LOG_ERROR( "Failed to build full URL to APM Server's intake API. snprintfRetVal: %d", snprintfRetVal );
        return;
    }
    curl_easy_setopt( curl, CURLOPT_URL, url );

    result = curl_easy_perform( curl );
    if ( result != CURLE_OK )
    {
        ELASTICAPM_LOG_ERROR( "Sending events to APM Server failed. URL: `%s'. Error message: `%s'.", url, curl_easy_strerror( result ) );
        return;
    }

    long responseCode;
    curl_easy_getinfo( curl, CURLINFO_RESPONSE_CODE, &responseCode );
    ELASTICAPM_LOG_DEBUG( "Sent events to APM Server. Response HTTP code: %ld", responseCode );
}

ResultCode elasticApmRequestShutdown()
{
    ResultCode resultCode;
    CURL* curl = NULL;
    enum { serializedEventsBufferSize = 1000 * 1000 };
    char* serializedEventsBuffer = NULL;
    TimePoint currentTime;
    Tracer* const tracer = getGlobalTracer();
    const ConfigSnapshot* const config = getTracerCurrentConfigSnapshot( tracer );
    Transaction* const currentTransaction = tracer->currentTransaction;

    ELASTICAPM_LOG_TRACE_FUNCTION_ENTRY();

    if ( !tracer->isInited )
    {
        resultCode = resultFailure;
        ELASTICAPM_LOG_TRACE_FUNCTION_EXIT_MSG( "Extension is not initialized" );
        goto finally;
    }

    if ( currentTransaction == NULL )
    {
        resultCode = resultSuccess;
        ELASTICAPM_LOG_TRACE_FUNCTION_EXIT_MSG( "Because there is no current transaction" );
        goto finally;
    }

    getCurrentTime( &currentTime );

    /* get a curl handle */
    curl = curl_easy_init();
    if ( curl == NULL )
    {
        resultCode = resultFailure;
        ELASTICAPM_LOG_TRACE_FUNCTION_EXIT_MSG( "Because curl_easy_init() returned NULL" );
        goto failure;
    }

    ELASTICAPM_EMALLOC_STRING_IF_FAILED_GOTO( serializedEventsBufferSize, serializedEventsBuffer );
    TextOutputStream serializedEventsTxtOutStream =
            makeTextOutputStream( serializedEventsBuffer, serializedEventsBufferSize );
    serializedEventsTxtOutStream.autoTermZero = false;

    appendMetadata( config, &serializedEventsTxtOutStream );

    // We ignore appendTransaction's ResultCode because we want to send the rest of the events even without the transaction
    appendTransaction( currentTransaction, &currentTime, &serializedEventsTxtOutStream );

    appendMetrics( &tracer->startSystemMetricsReading, &currentTime, &serializedEventsTxtOutStream );

    if ( currentTransaction->errorsBuffer != NULL )
        streamStringView( textOutputStreamContentAsStringView( &currentTransaction->errorsTextOutputStream ), &serializedEventsTxtOutStream );
    if ( currentTransaction->exceptionsBuffer != NULL )
        streamStringView( textOutputStreamContentAsStringView( &currentTransaction->exceptionsTextOutputStream ), &serializedEventsTxtOutStream );

    sendEventsToApmServer( curl, config, serializedEventsBuffer );

    resultCode = resultSuccess;
    ELASTICAPM_LOG_TRACE_FUNCTION_EXIT();

    finally:
    if ( curl != NULL ) curl_easy_cleanup( curl );
    ELASTICAPM_EFREE_STRING_AND_SET_TO_NULL( serializedEventsBufferSize, serializedEventsBuffer );
    deleteTransactionAndSetToNull( &tracer->currentTransaction );
    if ( isMemoryTrackingEnabled( &tracer->memTracker ) ) memoryTrackerRequestShutdown( &tracer->memTracker );

    return resultCode;

    failure:
    goto finally;
}
