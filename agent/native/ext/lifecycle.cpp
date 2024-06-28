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

#include "lifecycle.h"
#if defined(PHP_WIN32) && ! defined(CURL_STATICLIB)
#   define CURL_STATICLIB
#endif
#include <curl/curl.h>
#include <inttypes.h> // PRIu64
#include <stdbool.h>
#include <php.h>
#include <zend_compile.h>
#include <zend_exceptions.h>
#include <zend_builtin_functions.h>
#include "php_elastic_apm.h"
#include "log.h"
#include "ConfigSnapshot.h"
#include "SystemMetrics.h"
#include "php_error.h"
#include "util_for_PHP.h"
#include "elastic_apm_assert.h"
#include "MemoryTracker.h"
#include "supportability.h"
#include "elastic_apm_alloc.h"
#include "elastic_apm_API.h"
#include "tracer_PHP_part.h"
#include "backend_comm.h"
#include "AST_instrumentation.h"
#include "Hooking.h"
#include "CommonUtils.h"
#include "Diagnostics.h"
#include "Hooking.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_LIFECYCLE

static const char JSON_METRICSET[] =
        "{\"metricset\":{\"samples\":{\"system.cpu.total.norm.pct\":{\"value\":%.2f},\"system.process.cpu.total.norm.pct\":{\"value\":%.2f},\"system.memory.actual.free\":{\"value\":%" PRIu64 "},\"system.memory.total\":{\"value\":%" PRIu64 "},\"system.process.memory.size\":{\"value\":%" PRIu64 "},\"system.process.memory.rss.bytes\":{\"value\":%" PRIu64 "}},\"timestamp\":%" PRIu64 "}}\n";

static uint64_t requestCounter = 0;

static
String buildSupportabilityInfo( size_t supportInfoBufferSize, char* supportInfoBuffer )
{
    TextOutputStream txtOutStream = makeTextOutputStream( supportInfoBuffer, supportInfoBufferSize );
    TextOutputStreamState txtOutStreamStateOnEntryStart;
    if ( ! textOutputStreamStartEntry( &txtOutStream, &txtOutStreamStateOnEntryStart ) )
    {
        return ELASTIC_APM_TEXT_OUTPUT_STREAM_NOT_ENOUGH_SPACE_MARKER;
    }

    StructuredTextToOutputStreamPrinter structTxtToOutStreamPrinter;
    initStructuredTextToOutputStreamPrinter(
            /* in */ &txtOutStream
                     , /* prefix */ ELASTIC_APM_STRING_LITERAL_TO_VIEW( "" )
                     , /* out */ &structTxtToOutStreamPrinter );

    printSupportabilityInfo( (StructuredTextPrinter*) &structTxtToOutStreamPrinter );

    return textOutputStreamEndEntry( &txtOutStreamStateOnEntryStart, &txtOutStream );
}

void logSupportabilityInfo( LogLevel logLevel )
{
    ResultCode resultCode;
    enum { supportInfoBufferSize = 100 * 1000 + 1 };
    char* supportInfoBuffer = NULL;
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    String supportabilityInfo;
    StringView textRemainder;
    const char *textEnd;

    ELASTIC_APM_LOG_WITH_LEVEL( logLevel, "Version of agent C part: " PHP_ELASTIC_APM_VERSION );
    ELASTIC_APM_LOG_WITH_LEVEL( logLevel, "Current process command line: %s", streamCurrentProcessCommandLine( &txtOutStream, /* maxLength */ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ) );

    ELASTIC_APM_PEMALLOC_STRING_IF_FAILED_GOTO( supportInfoBufferSize, supportInfoBuffer );
    supportabilityInfo = buildSupportabilityInfo( supportInfoBufferSize, supportInfoBuffer );

    textEnd = supportabilityInfo + strlen( supportabilityInfo );
    textRemainder = makeStringViewFromBeginEnd( supportabilityInfo, textEnd );
    for ( ;; )
    {
        StringView eolSeq = findEndOfLineSequence( textRemainder );
        if ( isEmptyStringView( eolSeq ) ) break;

        ELASTIC_APM_LOG_WITH_LEVEL( logLevel, "%.*s", (int)( eolSeq.begin - textRemainder.begin), textRemainder.begin );
        textRemainder = makeStringViewFromBeginEnd( stringViewEnd( eolSeq ), textEnd );
    }
    ELASTIC_APM_LOG_WITH_LEVEL( logLevel, "%.*s", (int)( textEnd - textRemainder.begin), textRemainder.begin );

    resultCode = resultSuccess;
    finally:
    ELASTIC_APM_PEFREE_STRING_SIZE_AND_SET_TO_NULL( supportInfoBufferSize, supportInfoBuffer );
    ELASTIC_APM_UNUSED( resultCode );
    return;

    failure:
    goto finally;
}

static pid_t g_pidOnRequestInit = -1;

bool doesCurrentPidMatchPidOnInit( pid_t pidOnInit, String dbgDesc )
{
    pid_t currentPid = getCurrentProcessId();
    if ( pidOnInit != currentPid )
    {
        ELASTIC_APM_LOG_DEBUG( "Process ID on %s init doesn't match the current process ID"
                               " (maybe the current process is a child process forked after the init step?)"
                               "; PID on init: %d, current PID: %d, parent PID: %d"
                               , dbgDesc, (int)pidOnInit, (int)currentPid, (int)(getParentProcessId()) );
        return false;
    }
    return true;
}

typedef void (* ZendThrowExceptionHook )(
#if PHP_MAJOR_VERSION >= 8 /* if PHP version is 8.* and later */
        zend_object* exception
#else
        zval* exception
#endif
);

// static bool elasticApmZendErrorCallbackSet = false;
static bool elasticApmZendThrowExceptionHookSet = false;
static ZendThrowExceptionHook originalZendThrowExceptionHook = NULL;

void resetLastThrown() {
    zval_dtor(&ELASTICAPM_G(lastException));
    ZVAL_UNDEF(&ELASTICAPM_G(lastException));
}

void elasticApmZendThrowExceptionHookImpl(
#if PHP_MAJOR_VERSION >= 8 /* if PHP version is 8.* and later */
        zend_object* thrownAsPzobj
#else
        zval* thrownAsPzval
#endif
)
{

    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "lastException set: %s", boolToString( Z_TYPE(ELASTICAPM_G(lastException)) != IS_UNDEF ) );

    resetLastThrown();

#if PHP_MAJOR_VERSION >= 8 /* if PHP version is 8.* and later */
    zval thrownAsZval;
    zval* thrownAsPzval = &thrownAsZval;
    ZVAL_OBJ_COPY( /* dst: */ thrownAsPzval, /* src: */ thrownAsPzobj );
#endif
    ZVAL_COPY( /* pZvalDst: */ &ELASTICAPM_G(lastException), /* pZvalSrc: */ thrownAsPzval );

    ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT();
}

void elasticApmGetLastThrown(zval *return_value) {
    if (Z_TYPE(ELASTICAPM_G(lastException)) == IS_UNDEF) {
        RETURN_NULL();
    }

    RETURN_ZVAL(&ELASTICAPM_G(lastException), /* copy */ true, /* dtor */ false );
}

void elasticApmZendThrowExceptionHook(
#if PHP_MAJOR_VERSION >= 8 /* if PHP version is 8.* and later */
        zend_object* thrownObj
#else
        zval* thrownObj
#endif
)
{
    Tracer* const tracer = getGlobalTracer();
    const ConfigSnapshot* config = getTracerCurrentConfigSnapshot( tracer );
    if (config->captureErrors) {
        elasticApmZendThrowExceptionHookImpl( thrownObj );
    }

    if (originalZendThrowExceptionHook == elasticApmZendThrowExceptionHook) {
        ELASTIC_APM_LOG_CRITICAL( "originalZendThrowExceptionHook == elasticApmZendThrowExceptionHook" );
        return;
    }


    if ( originalZendThrowExceptionHook != NULL )
    {
        originalZendThrowExceptionHook( thrownObj );
    }
}


static void registerExceptionHooks() {
    if (!elasticApmZendThrowExceptionHookSet) {
        originalZendThrowExceptionHook = zend_throw_exception_hook;
        zend_throw_exception_hook = elasticApmZendThrowExceptionHook;
        elasticApmZendThrowExceptionHookSet = true;
        ELASTIC_APM_LOG_DEBUG( "Set zend_throw_exception_hook: %p (%s elasticApmZendThrowExceptionHook) -> %p"
                            , originalZendThrowExceptionHook, originalZendThrowExceptionHook == elasticApmZendThrowExceptionHook ? "==" : "!="
                            , elasticApmZendThrowExceptionHook );
    } else {
        ELASTIC_APM_LOG_WARNING( "zend_erzend_throw_exception_hook already set: %p. Original: %p, Elastic: %p", zend_throw_exception_hook, originalZendThrowExceptionHook, elasticApmZendThrowExceptionHook );
    }
}


static void unregisterExceptionHooks() {
    if (elasticApmZendThrowExceptionHookSet) {
        ZendThrowExceptionHook zendThrowExceptionHookBeforeRestore = zend_throw_exception_hook;
        zend_throw_exception_hook = originalZendThrowExceptionHook;
        ELASTIC_APM_LOG_DEBUG( "Restored zend_throw_exception_hook: %p (%s elasticApmZendThrowExceptionHook: %p) -> %p"
                               , zendThrowExceptionHookBeforeRestore, zendThrowExceptionHookBeforeRestore == elasticApmZendThrowExceptionHook ? "==" : "!="
                               , elasticApmZendThrowExceptionHook, originalZendThrowExceptionHook );
        originalZendThrowExceptionHook = NULL;
    } else {
        ELASTIC_APM_LOG_DEBUG("zend_throw_exception_hook not restored: %p, elastic: %p", zend_throw_exception_hook, elasticApmZendThrowExceptionHook);
    }
}

void elasticApmModuleInit( int moduleType, int moduleNumber )
{
    auto const &sapi = ELASTICAPM_G(globals)->sapi_;

    ELASTIC_APM_LOG_DIRECT_DEBUG( "%s entered: moduleType: %d, moduleNumber: %d, parent PID: %d, SAPI: %s (%d) is %s", __FUNCTION__, moduleType, moduleNumber, (int)(getParentProcessId()), sapi.getName().data(), static_cast<uint8_t>(sapi.getType()), sapi.isSupported() ? "supported" : "unsupported");

    if (!sapi.isSupported()) {
        return;
    }

    registerOsSignalHandler();

    elasticapm::php::Hooking::getInstance().fetchOriginalHooks();

    ResultCode resultCode;
    Tracer* const tracer = getGlobalTracer();
    const ConfigSnapshot* config = NULL;
    CURLcode curlCode;

    ELASTIC_APM_CALL_IF_FAILED_GOTO( constructTracer( tracer ) );

    if ( ! tracer->isInited )
    {
        ELASTIC_APM_LOG_DEBUG( "Extension is not initialized" );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    registerElasticApmIniEntries( moduleType, moduleNumber, &tracer->iniEntriesRegistrationState );

    ELASTIC_APM_CALL_IF_FAILED_GOTO( ensureLoggerInitialConfigIsLatest( tracer ) );
    ELASTIC_APM_CALL_IF_FAILED_GOTO( ensureAllComponentsHaveLatestConfig( tracer ) );

    logSupportabilityInfo( logLevel_debug );

    config = getTracerCurrentConfigSnapshot( tracer );

    if ( ! config->enabled )
    {
        resultCode = resultSuccess;
        ELASTIC_APM_LOG_DEBUG( "Extension is disabled" );
        goto finally;
    }

    registerCallbacksToLogFork();
    registerAtExitLogging();
    registerExceptionHooks();

    curlCode = curl_global_init( CURL_GLOBAL_ALL );
    if ( curlCode != CURLE_OK )
    {
        resultCode = resultFailure;
        ELASTIC_APM_LOG_ERROR( "curl_global_init failed: %s (%d)", curl_easy_strerror( curlCode ), (int)curlCode );
        goto finally;
    }
    tracer->curlInited = true;

    astInstrumentationOnModuleInit( config );

    elasticapm::php::Hooking::getInstance().replaceHooks();

    if (php_check_open_basedir_ex(config->bootstrapPhpPartFile, false) != 0) {
        ELASTIC_APM_LOG_WARNING(
            "Elastic Agent bootstrap file (%s) is located outside of paths allowed by open_basedir ini setting."
            " For more details see https://www.elastic.co/guide/en/apm/agent/php/current/setup.html#limitation-open_basedir"
            , config->bootstrapPhpPartFile
        );
    }

    resultCode = resultSuccess;
    finally:

    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    return;

    failure:
    moveTracerToFailedState( tracer );
    goto finally;
}

void elasticApmModuleShutdown( int moduleType, int moduleNumber )
{
    ResultCode resultCode;

    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "moduleType: %d, moduleNumber: %d", moduleType, moduleNumber );

    if (!ELASTICAPM_G(globals)->sapi_.isSupported()) {
        return;
    }

    Tracer* const tracer = getGlobalTracer();
    const ConfigSnapshot* const config = getTracerCurrentConfigSnapshot( tracer );

    if ( ! config->enabled )
    {
        resultCode = resultSuccess;
        ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT_MSG( "Because extension is not enabled" );
        goto finally;
    }

    elasticapm::php::Hooking::getInstance().restoreOriginalHooks();
    astInstrumentationOnModuleShutdown();

    unregisterExceptionHooks();

    backgroundBackendCommOnModuleShutdown( config );

    if ( tracer->curlInited )
    {
        curl_global_cleanup();
        tracer->curlInited = false;
    }

    unregisterElasticApmIniEntries( moduleType, moduleNumber, &tracer->iniEntriesRegistrationState );



    resultCode = resultSuccess;

    finally:
    destructTracer( tracer );

    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    ELASTIC_APM_UNUSED( resultCode );

    unregisterOsSignalHandler();

    ELASTIC_APM_LOG_DIRECT_DEBUG( "%s exiting...", __FUNCTION__ );
}

void elasticApmGetLastPhpError(zval* return_value) {
    if (!ELASTICAPM_G(lastErrorData)) {
        RETURN_NULL();
    }

    array_init( return_value );
    ELASTIC_APM_ZEND_ADD_ASSOC(return_value, "type", long, static_cast<zend_long>(ELASTICAPM_G(lastErrorData)->getType()));
    ELASTIC_APM_ZEND_ADD_ASSOC_NULLABLE_STRING( return_value, "fileName", ELASTICAPM_G(lastErrorData)->getFileName().data() );
    ELASTIC_APM_ZEND_ADD_ASSOC(return_value, "lineNumber", long, static_cast<zend_long>(ELASTICAPM_G(lastErrorData)->getLineNumber()));
    ELASTIC_APM_ZEND_ADD_ASSOC_NULLABLE_STRING( return_value, "message", ELASTICAPM_G(lastErrorData)->getMessage().data());
    Z_TRY_ADDREF_P((ELASTICAPM_G(lastErrorData)->getStackTrace()));
    ELASTIC_APM_ZEND_ADD_ASSOC(return_value, "stackTrace", zval, (ELASTICAPM_G(lastErrorData)->getStackTrace()));
}

auto buildPeriodicTaskExecutor() {
    auto periodicTaskExecutor = std::make_unique<elasticapm::php::PeriodicTaskExecutor>(
        std::vector<elasticapm::php::PeriodicTaskExecutor::task_t>{
        [inferredSpans = ELASTICAPM_G(globals)->inferredSpans_](elasticapm::php::PeriodicTaskExecutor::time_point_t now) { inferredSpans->tryRequestInterrupt(now); }
        },
        []() {
            // block signals for this thread to be handled by main Apache/PHP thread
            // list of signals from Apaches mpm handlers
            elasticapm::utils::blockSignal(SIGTERM);
            elasticapm::utils::blockSignal(SIGHUP);
            elasticapm::utils::blockSignal(SIGINT);
            elasticapm::utils::blockSignal(SIGWINCH);
            elasticapm::utils::blockSignal(SIGUSR1);
            elasticapm::utils::blockSignal(SIGPROF); // php timeout signal
        }
    );

    ELASTIC_APM_LOG_DEBUG("starting inferred spans thread");
    return periodicTaskExecutor;
}

void elasticApmRequestInit()
{
    if (!ELASTICAPM_G(globals)->sapi_.isSupported()) {
        return;
    }

    requestCounter++;

    Tracer* const tracer = getGlobalTracer();
    const ConfigSnapshot* config = getTracerCurrentConfigSnapshot( tracer );

    enableAccessToServerGlobal();
    bool preloadDetected = requestCounter == 1 ? detectOpcachePreload() : false;

    if (config && config->debugDiagnosticsFile && !preloadDetected && requestCounter <= 2) {
        if (ELASTICAPM_G(globals)->sharedMemory_->shouldExecuteOneTimeTaskAmongWorkers()) {
            try {
                elasticapm::utils::storeDiagnosticInformation(elasticapm::utils::getParameterizedString(config->debugDiagnosticsFile), *(ELASTICAPM_G(globals)->bridge_));
            } catch (std::exception const &e) {
                ELASTIC_APM_LOG_WARNING( "Unable to write agent diagnostics: %s", e.what() );
            }
        }
    }

    tracerPhpPartOnRequestInitSetInitialTracerState();

    TimePoint requestInitStartTime;
    getCurrentTime( &requestInitStartTime );

#if defined(ZTS) && defined(COMPILE_DL_ELASTIC_APM)
    ZEND_TSRMLS_CACHE_UPDATE();
#endif

    g_pidOnRequestInit = getCurrentProcessId();

    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "parent PID: %d", (int)(getParentProcessId()) );

    ResultCode resultCode;

    if ( ! tracer->isInited )
    {
        ELASTIC_APM_LOG_DEBUG( "Extension is not initialized" );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    if ( tracer->isFailed )
    {
        ELASTIC_APM_LOG_ERROR( "Extension is in failed state" );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    if (!isScriptRestricedByOpcacheAPI() && detectOpcacheRestartPending()) {
        ELASTIC_APM_LOG_WARNING("Detected that opcache reset is in a pending state. Instrumentation has been disabled for this request. There may be warnings or errors logged for this request.");
        resultCode = resultSuccess;
        goto finally;
    }

    if ( ! config->enabled )
    {
        ELASTIC_APM_LOG_DEBUG( "Not enabled" );
        resultCode = resultSuccess;
        goto finally;
    }

    if ( isMemoryTrackingEnabled( &tracer->memTracker ) ) memoryTrackerRequestInit( &tracer->memTracker );

    ELASTIC_APM_CALL_IF_FAILED_GOTO( ensureAllComponentsHaveLatestConfig( tracer ) );
    logSupportabilityInfo( logLevel_trace );

    enableAccessToServerGlobal();

    if (requestCounter == 1 && preloadDetected) {
        ELASTIC_APM_LOG_DEBUG( "opcache.preload request detected on init" );
        resultCode = resultSuccess;
        goto finally;
    }

    if (!config->captureErrors) {
        ELASTIC_APM_LOG_DEBUG( "capture_errors (captureErrors) configuration option is set to false which means errors will NOT be captured" );
    }
    ELASTICAPM_G(captureErrors) = config->captureErrors;

    if ( config->astProcessEnabled )
    {
        astInstrumentationOnRequestInit( config );
    }

    ELASTIC_APM_CALL_IF_FAILED_GOTO( tracerPhpPartOnRequestInit( config, &requestInitStartTime ) );

    if (config->profilingInferredSpansEnabled) {
        if (!ELASTICAPM_G(globals)->periodicTaskExecutor_) {
            ELASTICAPM_G(globals)->periodicTaskExecutor_ = buildPeriodicTaskExecutor();
        }

        std::chrono::milliseconds interval{50};
        try {
            if (config->profilingInferredSpansSamplingInterval) {
                interval = elasticapm::utils::convertDurationWithUnit(config->profilingInferredSpansSamplingInterval);
            }
        } catch (std::invalid_argument const &e) {
            ELASTIC_APM_LOG_ERROR( "profilingInferredSpansSamplingInterval '%s': '%s'", e.what(), config->profilingInferredSpansSamplingInterval);
        }

        if (interval.count() == 0) {
            interval = std::chrono::milliseconds{50};
            ELASTIC_APM_LOG_DEBUG("inferred spans thread interval too low, forced to default %zums", interval.count());
        }

        ELASTIC_APM_LOG_DEBUG("resuming inferred spans thread with sampling interval %zums", interval.count());
        ELASTICAPM_G(globals)->inferredSpans_->setInterval(interval);
        ELASTICAPM_G(globals)->inferredSpans_->reset();
        ELASTICAPM_G(globals)->periodicTaskExecutor_->setInterval(interval);
        ELASTICAPM_G(globals)->periodicTaskExecutor_->resumePeriodicTasks();
    }

    resultCode = resultSuccess;

    finally:
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
    return;

    failure:
    goto finally;
}

// static
// void appendMetrics( const SystemMetricsReading* startSystemMetricsReading, const TimePoint* currentTime, TextOutputStream* serializedEventsTxtOutStream )
// {
//     SystemMetricsReading endSystemMetricsReading;
//     readSystemMetrics( &endSystemMetricsReading );
//     SystemMetrics system_metrics;
//     getSystemMetrics( startSystemMetricsReading, &endSystemMetricsReading, &system_metrics );

//     streamPrintf(
//             serializedEventsTxtOutStream
//             , JSON_METRICSET
//             , system_metrics.machineCpu // system.cpu.total.norm.pct
//             , system_metrics.processCpu // system.process.cpu.total.norm.pct
//             , system_metrics.machineMemoryFree  // system.memory.actual.free
//             , system_metrics.machineMemoryTotal // system.memory.total
//             , system_metrics.processMemorySize  // system.process.memory.size
//             , system_metrics.processMemoryRss   // system.process.memory.rss.bytes
//             , timePointToEpochMicroseconds( currentTime ) );
// }

void elasticApmRequestShutdown()
{
    if (!ELASTICAPM_G(globals)->sapi_.isSupported()) {
        return;
    }

    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    Tracer* const tracer = getGlobalTracer();
    const ConfigSnapshot* const config = getTracerCurrentConfigSnapshot( tracer );

    if (!doesCurrentPidMatchPidOnInit( g_pidOnRequestInit, "request" )) {
        return;
    }

    if (!tracer->isInited) {
        ELASTIC_APM_LOG_TRACE( "Extension is not initialized" );
        return;
    }

    if ( ! config->enabled )
    {
        ELASTIC_APM_LOG_DEBUG( "Extension is not enabled" );
        return;
    }

    if (requestCounter == 1 && detectOpcachePreload()) {
        ELASTIC_APM_LOG_DEBUG( "opcache.preload request detected on shutdown" );
        return;
    }
	
    if (ELASTICAPM_G(globals)->periodicTaskExecutor_) {
        ELASTIC_APM_LOG_DEBUG("pausing inferred spans thread");
        ELASTICAPM_G(globals)->periodicTaskExecutor_->suspendPeriodicTasks();
    }

    ELASTICAPM_G(captureErrors) = false; // disabling error capturing on shutdown

    tracerPhpPartOnRequestShutdown();

    // there is no guarantee that following code will be executed - in case of error on php side

    ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT();
    // We ignore errors because we want the monitored application to continue working
    // even if APM encountered an issue that prevent it from working
}

#if PHP_VERSION_ID >= 80000
ZEND_RESULT_CODE  elasticApmRequestPostDeactivate(void) {
#else
int  elasticApmRequestPostDeactivate(void) {
#endif
    if (!ELASTICAPM_G(globals)->sapi_.isSupported()) {
        return SUCCESS;
    }

    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    if (requestCounter == 1 && detectOpcachePreload()) {
        ELASTIC_APM_LOG_DEBUG( "opcache.preload request detected on post deactivate" );
        return SUCCESS;
    }

    Tracer* const tracer = getGlobalTracer();
    const ConfigSnapshot* const config = getTracerCurrentConfigSnapshot( tracer );

    if ( config->astProcessEnabled )
    {
        astInstrumentationOnRequestShutdown();
    }

    resetCallInterceptionOnRequestShutdown();

    ELASTICAPM_G(lastErrorData).reset(nullptr);
    resetLastThrown();

    if ( tracer->isInited && isMemoryTrackingEnabled( &tracer->memTracker ) )
    {
        memoryTrackerRequestShutdown( &tracer->memTracker );
    }

    return SUCCESS;
}

static pid_t g_lastDetectedCurrentProcessId = -1;

ResultCode resetStateIfForkedChild( String dbgCalledFromFile, int dbgCalledFromLine, String dbgCalledFromFunction )
{
    ResultCode resultCode;
    pid_t lastDetectedCurrentProcessIdSaved;

    if ( g_lastDetectedCurrentProcessId == -1 )
    {
        g_lastDetectedCurrentProcessId = getCurrentProcessId();
        resultCode = resultSuccess;
        goto finally;
    }

    if ( g_lastDetectedCurrentProcessId == getCurrentProcessId() )
    {
        resultCode = resultSuccess;
        goto finally;
    }
    lastDetectedCurrentProcessIdSaved = g_lastDetectedCurrentProcessId;
    g_lastDetectedCurrentProcessId = getCurrentProcessId();

    ELASTIC_APM_CALL_IF_FAILED_GOTO( resetLoggingStateInForkedChild() );
    ELASTIC_APM_LOG_DEBUG( "Detected change in current process ID (PID) - handling it..."
                           "; old PID: %d; parent PID: %d"
                           , (int)lastDetectedCurrentProcessIdSaved, (int)(getParentProcessId()) );
    ELASTIC_APM_CALL_IF_FAILED_GOTO( resetBackgroundBackendCommStateInForkedChild() );

    resultCode = resultSuccess;
    finally:
    return resultCode;

    failure:
    goto finally;
}

ResultCode elasticApmEnterAgentCode( String dbgCalledFromFile, int dbgCalledFromLine, String dbgCalledFromFunction )
{
    ResultCode resultCode;

    // We SHOULD NOT log before resetting state if forked because logging might be using thread synchronization
    // which might deadlock in forked child
    ELASTIC_APM_CALL_IF_FAILED_GOTO( resetStateIfForkedChild( dbgCalledFromFile, dbgCalledFromLine, dbgCalledFromFunction ) );

    resultCode = resultSuccess;
    finally:
    return resultCode;

    failure:
    goto finally;
}