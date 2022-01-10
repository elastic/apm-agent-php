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

#include "backend_comm.h"
#include "elastic_apm_version.h"
#if defined(PHP_WIN32) && ! defined(CURL_STATICLIB)
#   define CURL_STATICLIB
#endif
#include <curl/curl.h>
#include "platform.h"
#include "elastic_apm_alloc.h"
#include "Tracer.h"
#include "ConfigManager.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_BACKEND_COMM


// Log response
static
size_t logResponse( void* data, size_t unusedSizeParam, size_t dataSize, void* unusedUserDataParam )
{
    // https://curl.haxx.se/libcurl/c/CURLOPT_WRITEFUNCTION.html
    // size (unusedSizeParam) is always 1
    ELASTIC_APM_UNUSED( unusedSizeParam );
    ELASTIC_APM_UNUSED( unusedUserDataParam );

    ELASTIC_APM_LOG_DEBUG( "APM Server's response body [length: %"PRIu64"]: %.*s", (UInt64) dataSize, (int) dataSize, (const char*) data );
    return dataSize;
}

#define ELASTIC_APM_CURL_EASY_SETOPT( curl, curlOptionId, ... ) \
    do { \
        CURLcode curl_easy_setopt_ret_val = curl_easy_setopt( curl, curlOptionId, __VA_ARGS__ ); \
        if ( curl_easy_setopt_ret_val != CURLE_OK ) \
        { \
            ELASTIC_APM_LOG_ERROR( "Failed to set cUrl option. curlOptionId: %d.", curlOptionId ); \
            ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE_EX( resultCurlFailure ); \
        } \
    } while ( false ) \
    /**/

ResultCode syncSendEventsToApmServer(
        bool disableSend
        , double serverTimeoutMilliseconds
        , const ConfigSnapshot* config
        , StringView serializedEvents )
{
    long serverTimeoutMillisecondsLong = (long) ceil( serverTimeoutMilliseconds );
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG(
            "Sending events to APM Server..."
            " disableSend: %s"
            " serverTimeoutMilliseconds: %f (as integer: %"PRIu64")"
            " serializedEvents [length: %"PRIu64"]:\n%.*s"
            , boolToString( disableSend )
            , serverTimeoutMilliseconds, (UInt64) serverTimeoutMillisecondsLong
            , (UInt64) serializedEvents.length, (int) serializedEvents.length, serializedEvents.begin );

    if ( disableSend )
    {
        ELASTIC_APM_LOG_DEBUG( "disable_send (disableSend) configuration option is set to true - discarding events instead of sending" );
        return resultSuccess;
    }

    ResultCode resultCode;
    CURL* curl = NULL;
    CURLcode result;
    enum
    {
        authBufferSize = 256
    };
    char auth[authBufferSize];
    enum
    {
        userAgentBufferSize = 100
    };
    char userAgent[userAgentBufferSize];
    enum
    {
        urlBufferSize = 256
    };
    char url[urlBufferSize];
    struct curl_slist* chunk = NULL;
    int snprintfRetVal;
    const char* authKind = NULL;
    const char* authValue = NULL;

    /* get a curl handle */
    curl = curl_easy_init();
    if ( curl == NULL )
    {
        ELASTIC_APM_LOG_ERROR( "curl_easy_init() returned NULL" );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    ELASTIC_APM_CURL_EASY_SETOPT( curl, CURLOPT_POST, 1L );
    ELASTIC_APM_CURL_EASY_SETOPT( curl, CURLOPT_POSTFIELDS, serializedEvents.begin );
    ELASTIC_APM_CURL_EASY_SETOPT( curl, CURLOPT_POSTFIELDSIZE, serializedEvents.length );
    ELASTIC_APM_CURL_EASY_SETOPT( curl, CURLOPT_WRITEFUNCTION, logResponse );

    if ( serverTimeoutMillisecondsLong == 0 )
    {
        ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG(
                "Timeout is disabled. serverTimeoutMilliseconds: %f (as integer: %"PRIu64")"
                , serverTimeoutMilliseconds, (UInt64) serverTimeoutMillisecondsLong );
    }
    else
    {
        ELASTIC_APM_CURL_EASY_SETOPT( curl, CURLOPT_TIMEOUT_MS, serverTimeoutMillisecondsLong );
    }

    if ( ! config->verifyServerCert )
    {
        ELASTIC_APM_LOG_DEBUG( "verify_server_cert configuration option is set to false"
                               " - disabling SSL/TLS certificate verification for communication with APM Server..." );
        ELASTIC_APM_CURL_EASY_SETOPT( curl, CURLOPT_SSL_VERIFYPEER, 0L );
    }

    // Authorization with API key or secret token if present
    if ( ! isNullOrEmtpyString( config->apiKey ) )
    {
        authKind = "ApiKey";
        authValue = config->apiKey;
    }
    else if ( ! isNullOrEmtpyString( config->secretToken ) )
    {
        authKind = "Bearer";
        authValue = config->secretToken;
    }

    if ( authValue != NULL )
    {
        snprintfRetVal = snprintf( auth, authBufferSize, "Authorization: %s %s", authKind, authValue );
        if ( snprintfRetVal < 0 || snprintfRetVal >= authBufferSize )
        {
            ELASTIC_APM_LOG_ERROR( "Failed to build Authorization header."
                                   " snprintfRetVal: %d. authKind: %s. authValue: %s.", snprintfRetVal, authKind, authValue );
            ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
        }
        ELASTIC_APM_LOG_TRACE( "Adding header: %s", auth );
        chunk = curl_slist_append( chunk, auth );
    }
    chunk = curl_slist_append( chunk, "Content-Type: application/x-ndjson" );
    ELASTIC_APM_CURL_EASY_SETOPT( curl, CURLOPT_HTTPHEADER, chunk );

    // User agent - "elasticapm-<language>/<version>" (no separators between "elastic" and "apm" is on purpose)
    // For example, "elasticapm-java/1.2.3" or "elasticapm-dotnet/1.2.3"
    snprintfRetVal = snprintf( userAgent, userAgentBufferSize, "elasticapm-php/%s", PHP_ELASTIC_APM_VERSION );
    if ( snprintfRetVal < 0 || snprintfRetVal >= authBufferSize )
    {
        ELASTIC_APM_LOG_ERROR( "Failed to build User-Agent header. snprintfRetVal: %d", snprintfRetVal );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }
    ELASTIC_APM_CURL_EASY_SETOPT( curl, CURLOPT_USERAGENT, userAgent );

    snprintfRetVal = snprintf( url, urlBufferSize, "%s/intake/v2/events", config->serverUrl );
    if ( snprintfRetVal < 0 || snprintfRetVal >= authBufferSize )
    {
        ELASTIC_APM_LOG_ERROR( "Failed to build full URL to APM Server's intake API. snprintfRetVal: %d", snprintfRetVal );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }
    ELASTIC_APM_CURL_EASY_SETOPT( curl, CURLOPT_URL, url );

    result = curl_easy_perform( curl );
    if ( result != CURLE_OK )
    {
        char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
        TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
        ELASTIC_APM_LOG_ERROR(
                "Sending events to APM Server failed."
                " URL: `%s'."
                " Error message: `%s'."
                " Current process command line: `%s'"
                , url
                , curl_easy_strerror( result )
                , streamCurrentProcessCommandLine( &txtOutStream ) );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    long responseCode;
    curl_easy_getinfo( curl, CURLINFO_RESPONSE_CODE, &responseCode );
    ELASTIC_APM_LOG_DEBUG( "Sent events to APM Server. Response HTTP code: %ld. URL: `%s'.", responseCode, url );

    resultCode = resultSuccess;

    finally:
    if ( curl != NULL ) curl_easy_cleanup( curl );

    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    return resultCode;

    failure:
    goto finally;
}

#undef ELASTIC_APM_CURL_EASY_SETOPT

struct DataToSendNode;
typedef struct DataToSendNode DataToSendNode;

struct DataToSendNode
{
    UInt64 id;

    DataToSendNode* prev;
    DataToSendNode* next;

    bool disableSend;
    double serverTimeoutMilliseconds;
    StringView serializedEvents;
};

static void freeDataToSendNode( DataToSendNode** nodeOutPtr )
{
    ELASTIC_APM_ASSERT_VALID_IN_PTR_TO_PTR( nodeOutPtr );

    ELASTIC_APM_FREE_AND_SET_TO_NULL( char, (*nodeOutPtr)->serializedEvents.length, /* in,out */ (*nodeOutPtr)->serializedEvents.begin );
    ELASTIC_APM_FREE_INSTANCE_AND_SET_TO_NULL( DataToSendNode, /* in,out */ *nodeOutPtr );
}

struct DataToSendQueue
{
    DataToSendNode head;
    DataToSendNode tail;
};
typedef struct DataToSendQueue DataToSendQueue;

static void initDataToSendQueue( DataToSendQueue* dataQueue )
{
    ELASTIC_APM_ASSERT_VALID_PTR( dataQueue );

    dataQueue->head.prev =  NULL;
    dataQueue->head.next =  &dataQueue->tail;
    dataQueue->tail.prev =  &dataQueue->head;
    dataQueue->tail.next =  NULL;
}

static ResultCode addCopyToDataToSendQueue( DataToSendQueue* dataQueue
                                            , UInt64 id
                                            , bool disableSend
                                            , double serverTimeoutMilliseconds
                                            , StringView serializedEvents )
{
    ELASTIC_APM_ASSERT_VALID_PTR( dataQueue );

    ResultCode resultCode;
    char* dataCopy = NULL;
    DataToSendNode* newNode = NULL;

    ELASTIC_APM_MALLOC_INSTANCE_IF_FAILED_GOTO( DataToSendNode, /* out */ newNode );
    newNode->serializedEvents.begin = NULL;
    ELASTIC_APM_MALLOC_IF_FAILED_GOTO_EX( void, serializedEvents.length, /* out */ dataCopy );

    resultCode = resultSuccess;

    memcpy( dataCopy, serializedEvents.begin, serializedEvents.length );

    newNode->serializedEvents.begin = dataCopy;
    newNode->serializedEvents.length = serializedEvents.length;

    newNode->id = id;
    newNode->disableSend = disableSend;
    newNode->serverTimeoutMilliseconds = serverTimeoutMilliseconds;

    newNode->next = &( dataQueue->tail );
    newNode->prev = dataQueue->tail.prev;
    dataQueue->tail.prev->next = newNode;
    dataQueue->tail.prev = newNode;

    finally:
    return resultCode;

    failure:
    ELASTIC_APM_MALLOC_IF_FAILED_GOTO_EX( void, serializedEvents.length, /* out */ dataCopy );
    freeDataToSendNode( &newNode );
    goto finally;
}

static
bool isDataToSendQueueEmpty( const DataToSendQueue* dataQueue )
{
    ELASTIC_APM_ASSERT_VALID_PTR( dataQueue );

    return dataQueue->head.next == &( dataQueue->tail );
}

DataToSendNode* getFirstNodeInDataToSendQueue( DataToSendQueue* dataQueue )
{
    ELASTIC_APM_ASSERT_VALID_PTR( dataQueue );

    return isDataToSendQueueEmpty( dataQueue ) ? NULL : dataQueue->head.next;
}

size_t removeFirstNodeInDataToSendQueue( DataToSendQueue* dataQueue )
{
    ELASTIC_APM_ASSERT_VALID_PTR( dataQueue );
    ELASTIC_APM_ASSERT( ! isDataToSendQueueEmpty( dataQueue ), "" );

    DataToSendNode* firstNode = dataQueue->head.next;
    size_t firstNodeDataSize = firstNode->serializedEvents.length;
    DataToSendNode* newFirstNode = firstNode->next;

    dataQueue->head.next = newFirstNode;
    newFirstNode->prev = &( dataQueue->head );

    freeDataToSendNode( &firstNode );

    return firstNodeDataSize;
}

static void freeDataToSendQueue( DataToSendQueue* dataQueue )
{
    ELASTIC_APM_ASSERT_VALID_PTR( dataQueue );

    while ( ! isDataToSendQueueEmpty( dataQueue ) )
    {
        removeFirstNodeInDataToSendQueue( dataQueue );
    }
}

#define ELASTIC_APM_MAX_QUEUE_SIZE_IN_BYTES (10 * 1024 * 1024)

struct BackgroundBackendComm
{
    int refCount;
    Mutex* mutex;
    ConditionVariable* condVar;
    Thread* thread;
    DataToSendQueue dataToSendQueue;
    size_t dataToSendTotalSize;
    size_t nextEventsBatchId;
    double lastServerTimeoutMilliseconds;
    bool shouldExit;
    TimeSpec shouldExitBy;
};
typedef struct BackgroundBackendComm BackgroundBackendComm;

struct BackgroundBackendCommSharedStateSnapshot
{
    DataToSendNode* firstDataToSendNode;
    size_t dataToSendTotalSize;
    bool shouldExit;
    TimeSpec shouldExitBy;
};
typedef struct BackgroundBackendCommSharedStateSnapshot BackgroundBackendCommSharedStateSnapshot;

#define ELASTIC_APM_BACKGROUND_BACKEND_COMM_DO_UNDER_LOCK_PROLOG() \
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY(); \
    ELASTIC_APM_ASSERT_VALID_PTR( backgroundBackendComm ); \
    ResultCode resultCode; \
    bool shouldUnlockMutex = false; \
    ELASTIC_APM_CALL_IF_FAILED_GOTO( lockMutex( backgroundBackendComm->mutex, &shouldUnlockMutex, __FUNCTION__ ) );

#define ELASTIC_APM_BACKGROUND_BACKEND_COMM_DO_UNDER_LOCK_EPILOG() \
    finally: \
    unlockMutex( backgroundBackendComm->mutex, &shouldUnlockMutex, __FUNCTION__ ); \
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT(); \
    return resultCode; \
    failure: \
    goto finally;

ResultCode getSharedStateSnapshotUnderLock( BackgroundBackendComm* backgroundBackendComm
                                            , BackgroundBackendCommSharedStateSnapshot* sharedStateSnapshotOut )
{
    ELASTIC_APM_BACKGROUND_BACKEND_COMM_DO_UNDER_LOCK_PROLOG()

    ELASTIC_APM_ASSERT_VALID_PTR( sharedStateSnapshotOut );

    sharedStateSnapshotOut->firstDataToSendNode = getFirstNodeInDataToSendQueue( &( backgroundBackendComm->dataToSendQueue ) );
    sharedStateSnapshotOut->dataToSendTotalSize = backgroundBackendComm->dataToSendTotalSize;
    sharedStateSnapshotOut->shouldExit = backgroundBackendComm->shouldExit;
    sharedStateSnapshotOut->shouldExitBy = backgroundBackendComm->shouldExitBy;

    ELASTIC_APM_BACKGROUND_BACKEND_COMM_DO_UNDER_LOCK_EPILOG()
}

ResultCode removeFirstInDataToSendQueueUnderLock( BackgroundBackendComm* backgroundBackendComm )
{
    ELASTIC_APM_BACKGROUND_BACKEND_COMM_DO_UNDER_LOCK_PROLOG()

    size_t firstNodeDataSize = removeFirstNodeInDataToSendQueue( &( backgroundBackendComm->dataToSendQueue ) );
    backgroundBackendComm->dataToSendTotalSize -= firstNodeDataSize;

    ELASTIC_APM_BACKGROUND_BACKEND_COMM_DO_UNDER_LOCK_EPILOG()
}

ResultCode waitForChangesInSharedState( BackgroundBackendComm* backgroundBackendComm )
{
    ELASTIC_APM_BACKGROUND_BACKEND_COMM_DO_UNDER_LOCK_PROLOG()

    ELASTIC_APM_CALL_IF_FAILED_GOTO( waitConditionVariable( backgroundBackendComm->condVar, backgroundBackendComm->mutex, __FUNCTION__ ) );
    ELASTIC_APM_LOG_DEBUG( "Waiting exited" );

    ELASTIC_APM_BACKGROUND_BACKEND_COMM_DO_UNDER_LOCK_EPILOG()
}

#undef ELASTIC_APM_BACKGROUND_BACKEND_COMM_DO_UNDER_LOCK_EPILOG
#undef ELASTIC_APM_BACKGROUND_BACKEND_COMM_DO_UNDER_LOCK_PROLOG

void* backgroundBackendCommThreadFunc( void* arg )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    ELASTIC_APM_ASSERT_VALID_PTR( arg );

    ResultCode resultCode;
    BackgroundBackendComm* backgroundBackendComm = (BackgroundBackendComm*)arg;
    const ConfigSnapshot* config = getTracerCurrentConfigSnapshot( getGlobalTracer() );

    while ( true )
    {
        BackgroundBackendCommSharedStateSnapshot sharedStateSnapshot;
        ELASTIC_APM_CALL_IF_FAILED_GOTO( getSharedStateSnapshotUnderLock( backgroundBackendComm, &sharedStateSnapshot ) );

        char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
        TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

        ELASTIC_APM_LOG_TRACE( "Shared state snapshot: "
                                " total size of queued events: %"PRIu64
                                ", firstDataToSendNode %s NULL"
                                " (size: %"PRIu64 ")"
                                ", shouldExit: %s"
                                ", shouldExitBy: %s"
                               , (UInt64) sharedStateSnapshot.dataToSendTotalSize
                               , sharedStateSnapshot.firstDataToSendNode == NULL ? "==" : "!="
                               , (UInt64)( sharedStateSnapshot.firstDataToSendNode == NULL ? 0 : sharedStateSnapshot.firstDataToSendNode->serializedEvents.length )
                               , boolToString( sharedStateSnapshot.shouldExit )
                               , sharedStateSnapshot.shouldExit ? streamUtcTimeSpecAsLocal( &sharedStateSnapshot.shouldExitBy, &txtOutStream ) : "N/A" );

        ELASTIC_APM_ASSERT( (sharedStateSnapshot.dataToSendTotalSize == 0) == ( sharedStateSnapshot.firstDataToSendNode == NULL )
                            , "dataToSendTotalSize: %"PRIu64 ", firstDataToSendNode: %p (size: %"PRIu64 ")"
                            , (UInt64) sharedStateSnapshot.dataToSendTotalSize
                            , sharedStateSnapshot.firstDataToSendNode
                            , (UInt64)( sharedStateSnapshot.firstDataToSendNode == NULL ? 0 : sharedStateSnapshot.firstDataToSendNode->serializedEvents.length ) );

        const bool isDataToSendQueueEmpty = sharedStateSnapshot.firstDataToSendNode == NULL;
        TimeSpec now;
        ELASTIC_APM_CALL_IF_FAILED_GOTO( getCurrentAbsTimeSpec( /* out */ &now ) );

        if ( sharedStateSnapshot.shouldExit && ( isDataToSendQueueEmpty || compareAbsTimeSpecs( &sharedStateSnapshot.shouldExitBy, &now ) < 0 ) )
        {
            break;
        }

        if ( isDataToSendQueueEmpty )
        {
            ELASTIC_APM_CALL_IF_FAILED_GOTO( waitForChangesInSharedState( backgroundBackendComm ) );
            continue;
        }

        ELASTIC_APM_LOG_DEBUG(
                "About to send batch of events"
                "; batch ID: %"PRIu64
                "; batch size: %"PRIu64
                "; total size of queued events: %"PRIu64
                , (UInt64) sharedStateSnapshot.firstDataToSendNode->id
                , (UInt64) sharedStateSnapshot.firstDataToSendNode->serializedEvents.length
                , (UInt64) sharedStateSnapshot.dataToSendTotalSize );

        resultCode = syncSendEventsToApmServer( sharedStateSnapshot.firstDataToSendNode->disableSend
                                                , sharedStateSnapshot.firstDataToSendNode->serverTimeoutMilliseconds
                                                , config
                                                , sharedStateSnapshot.firstDataToSendNode->serializedEvents );
        if ( resultCode != resultSuccess )
        {
            ELASTIC_APM_LOG_ERROR(
                    "Failed to send batch of events - the batch will be dequeued and dropped"
                    "; batch ID: %"PRIu64
                    "; batch size: %"PRIu64
                    "; total size of queued events: %"PRIu64
                    , (UInt64) sharedStateSnapshot.firstDataToSendNode->id
                    , (UInt64) sharedStateSnapshot.firstDataToSendNode->serializedEvents.length
                    , (UInt64) sharedStateSnapshot.dataToSendTotalSize );
        }

        // We remove the node even if we have just failed to send the data
        ELASTIC_APM_CALL_IF_FAILED_GOTO( removeFirstInDataToSendQueueUnderLock( backgroundBackendComm ) );
    }

    resultCode = resultSuccess;

    finally:
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    return NULL;

    failure:
    goto finally;
}

ResultCode unwindBackgroundBackendComm( BackgroundBackendComm** backgroundBackendCommOutPtr, const TimeSpec* timeoutAbsUtc )
{
    ELASTIC_APM_ASSERT_VALID_PTR( backgroundBackendCommOutPtr );
    // ELASTIC_APM_ASSERT_VALID_PTR( timeoutAbsUtc ); <- timeoutAbsUtc can be NULL

    ResultCode resultCode;

    BackgroundBackendComm* backgroundBackendComm = *backgroundBackendCommOutPtr;
    if ( backgroundBackendComm == NULL )
    {
        resultCode = resultSuccess;
        goto finally;
    }

    if ( backgroundBackendComm->thread != NULL )
    {
        void* backgroundBackendCommThreadFuncRetVal = NULL;
        bool hasTimedOut;
        ELASTIC_APM_CALL_IF_FAILED_GOTO(
                timedJoinAndDeleteThread( &( backgroundBackendComm->thread ), &backgroundBackendCommThreadFuncRetVal, timeoutAbsUtc, &hasTimedOut, __FUNCTION__ ) );
    }

    if ( backgroundBackendComm->condVar != NULL )
    {
        ELASTIC_APM_CALL_IF_FAILED_GOTO( deleteConditionVariable( &( backgroundBackendComm->condVar ) ) );
    }

    if ( backgroundBackendComm->mutex != NULL )
    {
        ELASTIC_APM_CALL_IF_FAILED_GOTO( deleteMutex( &( backgroundBackendComm->mutex ) ) );
    }

    resultCode = resultSuccess;
    freeDataToSendQueue( &( backgroundBackendComm->dataToSendQueue ) );
    ELASTIC_APM_FREE_INSTANCE_AND_SET_TO_NULL( BackgroundBackendComm, *backgroundBackendCommOutPtr );

    finally:
    return resultCode;

    failure:
    goto finally;
}

static Mutex* g_backgroundBackendCommMutex = NULL;
static BackgroundBackendComm* g_backgroundBackendComm = NULL;

ResultCode backgroundBackendCommOnModuleInit( const ConfigSnapshot* config )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "g_backgroundBackendCommMutex: %p; g_backgroundBackendComm: %p"
                                              , g_backgroundBackendCommMutex, g_backgroundBackendComm );

    ResultCode resultCode;

    if ( g_backgroundBackendCommMutex != NULL )
    {
        ELASTIC_APM_LOG_ERROR( "Unexpected state: g_backgroundBackendCommMutex != NULL" );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    if ( g_backgroundBackendComm != NULL )
    {
        ELASTIC_APM_LOG_ERROR( "Unexpected state: g_backgroundBackendComm != NULL" );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    if ( ! config->asyncBackendComm )
    {
        ELASTIC_APM_LOG_DEBUG( "async_backend_comm (asyncBackendComm) configuration option is set to false - no need to start background backend comm" );
        resultCode = resultSuccess;
        goto finally;
    }

    ELASTIC_APM_CALL_IF_FAILED_GOTO( newMutex( &( g_backgroundBackendCommMutex ), /* dbgDesc */ "g_backgroundBackendCommMutex" ) );

    finally:
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    return resultCode;

    failure:
    if ( g_backgroundBackendCommMutex != NULL )
    {
        deleteMutex( &g_backgroundBackendCommMutex );
    }
    goto finally;
}

ResultCode newBackgroundBackendComm( const ConfigSnapshot* config, BackgroundBackendComm** backgroundBackendCommOut )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    ResultCode resultCode;
    BackgroundBackendComm* backgroundBackendComm = NULL;

    ELASTIC_APM_MALLOC_INSTANCE_IF_FAILED_GOTO( BackgroundBackendComm, /* out */ backgroundBackendComm );
    backgroundBackendComm->refCount = 1;
    backgroundBackendComm->condVar = NULL;
    backgroundBackendComm->mutex = NULL;
    backgroundBackendComm->thread = NULL;
    initDataToSendQueue( &( backgroundBackendComm->dataToSendQueue ) );
    backgroundBackendComm->dataToSendTotalSize = 0;
    backgroundBackendComm->nextEventsBatchId = 1;
    backgroundBackendComm->lastServerTimeoutMilliseconds = 0;
    backgroundBackendComm->shouldExit = false;
    ELASTIC_APM_CALL_IF_FAILED_GOTO( newMutex( &( backgroundBackendComm->mutex ), /* dbgDesc */ "Background backend communications" ) );
    ELASTIC_APM_CALL_IF_FAILED_GOTO( newConditionVariable( &( backgroundBackendComm->condVar ), /* dbgDesc */ "Background backend communications" ) );

    backgroundBackendComm->refCount = 2;
    resultCode = newThread( &( backgroundBackendComm->thread )
                            , &backgroundBackendCommThreadFunc
                            , /* threadFuncArg: */ backgroundBackendComm
                            , /* thread's dbgDesc */ "Background backend communications" );
    if ( resultCode == resultSuccess )
    {
        ELASTIC_APM_LOG_DEBUG( "Started thread for background backend comm; thread ID: %"PRIu64, getThreadId( backgroundBackendComm->thread ) );
    }
    else
    {
        --backgroundBackendComm->refCount;
    }

    resultCode = resultSuccess;
    *backgroundBackendCommOut = backgroundBackendComm;

    finally:
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    return resultCode;

    failure:
    unwindBackgroundBackendComm( &backgroundBackendComm, /* timeoutAbsUtc: */ NULL );
    goto finally;
}

ResultCode backgroundBackendCommOnRequestInit( const ConfigSnapshot* config )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "g_backgroundBackendCommMutex: %p", g_backgroundBackendCommMutex );

    ResultCode resultCode;
    bool shouldUnlockMutex = false;

    if ( ! config->asyncBackendComm )
    {
        ELASTIC_APM_LOG_DEBUG( "async_backend_comm (asyncBackendComm) configuration option is set to false - no need to start background backend comm" );
        resultCode = resultSuccess;
        goto finally;
    }

    if ( g_backgroundBackendCommMutex == NULL )
    {
        ELASTIC_APM_LOG_ERROR( "Unexpected state: g_backgroundBackendCommMutex == NULL" );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }
    ELASTIC_APM_CALL_IF_FAILED_GOTO( lockMutex( g_backgroundBackendCommMutex, &shouldUnlockMutex, __FUNCTION__ ) );

    if ( g_backgroundBackendComm == NULL )
    {
        ELASTIC_APM_CALL_IF_FAILED_GOTO( newBackgroundBackendComm( config, &g_backgroundBackendComm ) );
    }

    resultCode = resultSuccess;

    finally:
    unlockMutex( g_backgroundBackendCommMutex, &shouldUnlockMutex, __FUNCTION__ );
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    return resultCode;

    failure:
    goto finally;
}

static
ResultCode signalBackgroundBackendCommThreadToExit( BackgroundBackendComm* backgroundBackendComm, /* out */ TimeSpec* shouldExitBy )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    ResultCode resultCode;
    bool shouldUnlockMutex = false;

    ELASTIC_APM_CALL_IF_FAILED_GOTO( lockMutex( backgroundBackendComm->mutex, &shouldUnlockMutex, __FUNCTION__ ) );

    backgroundBackendComm->shouldExit = true;

    ELASTIC_APM_CALL_IF_FAILED_GOTO( getCurrentAbsTimeSpec( /* out */ shouldExitBy ) );
    addDelayToAbsTimeSpec( /* in, out */ shouldExitBy, lround( backgroundBackendComm->lastServerTimeoutMilliseconds * ELASTIC_APM_NUMBER_OF_NANOSECONDS_IN_MILLISECOND ) );
    backgroundBackendComm->shouldExitBy = *shouldExitBy;
    ELASTIC_APM_CALL_IF_FAILED_GOTO( signalConditionVariable( backgroundBackendComm->condVar, __FUNCTION__ ) );

    resultCode = resultSuccess;

    finally:
    unlockMutex( backgroundBackendComm->mutex, &shouldUnlockMutex, __FUNCTION__ );
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    return resultCode;

    failure:
    goto finally;
}

void backgroundBackendCommOnModuleShutdown()
{
    BackgroundBackendComm* backgroundBackendComm = g_backgroundBackendComm;

    if ( backgroundBackendComm == NULL )
    {
        return;
    }

    ELASTIC_APM_ASSERT( g_backgroundBackendCommMutex != NULL, "%p", g_backgroundBackendCommMutex );

    ResultCode resultCode;
    TimeSpec shouldExitBy;

    ELASTIC_APM_CALL_IF_FAILED_GOTO( signalBackgroundBackendCommThreadToExit( backgroundBackendComm, /* out */ &shouldExitBy ) );
    ELASTIC_APM_CALL_IF_FAILED_GOTO( unwindBackgroundBackendComm( &backgroundBackendComm, &shouldExitBy ) );
    resultCode = resultSuccess;

    finally:

    g_backgroundBackendComm = NULL;
    ELASTIC_APM_CALL_IF_FAILED_GOTO( deleteMutex( &g_backgroundBackendCommMutex ) );
    return;

    failure:
    goto finally;
}

static
ResultCode enqueueEventsToSendToApmServer(
        bool disableSend
        , double serverTimeoutMilliseconds
        , StringView serializedEvents )
{
    long serverTimeoutMillisecondsLong = (long) ceil( serverTimeoutMilliseconds );
    ELASTIC_APM_LOG_DEBUG(
            "Queueing events to send asynchronously..."
            " disableSend: %s"
            " serverTimeoutMilliseconds: %f (as integer: %"PRIu64")"
            " serializedEvents [length: %"PRIu64"]:\n%.*s"
            , boolToString( disableSend )
            , serverTimeoutMilliseconds, (UInt64) serverTimeoutMillisecondsLong
            , (UInt64) serializedEvents.length, (int) serializedEvents.length, serializedEvents.begin );

    ResultCode resultCode;
    bool shouldUnlockMutex = false;
    BackgroundBackendComm* backgroundBackendComm = g_backgroundBackendComm;

    ELASTIC_APM_CALL_IF_FAILED_GOTO( lockMutex( backgroundBackendComm->mutex, &shouldUnlockMutex, __FUNCTION__ ) );

    if ( backgroundBackendComm->dataToSendTotalSize >= ELASTIC_APM_MAX_QUEUE_SIZE_IN_BYTES )
    {
        ELASTIC_APM_LOG_ERROR(
                "Already queued events are above max queue size - dropping these events"
                "; size of already queued events: %"PRIu64
                , (UInt64) backgroundBackendComm->dataToSendTotalSize );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    const UInt64 id = backgroundBackendComm->nextEventsBatchId;
    ELASTIC_APM_CALL_IF_FAILED_GOTO(
            addCopyToDataToSendQueue( &( backgroundBackendComm->dataToSendQueue )
                                      , id
                                      , disableSend
                                      , serverTimeoutMilliseconds
                                      , serializedEvents ) );

    backgroundBackendComm->dataToSendTotalSize += serializedEvents.length;
    ++backgroundBackendComm->nextEventsBatchId;
    backgroundBackendComm->lastServerTimeoutMilliseconds = serverTimeoutMilliseconds;

    ELASTIC_APM_LOG_DEBUG(
            "Queued a batch of events"
            "; batch ID: %"PRIu64
            "; batch size: %"PRIu64
            "; total size of queued events: %"PRIu64
            , (UInt64) id
            , (UInt64) serializedEvents.length
            , (UInt64) backgroundBackendComm->dataToSendTotalSize );

    ELASTIC_APM_CALL_IF_FAILED_GOTO( signalConditionVariable( backgroundBackendComm->condVar, __FUNCTION__ ) );

    resultCode = resultSuccess;

    finally:
    unlockMutex( backgroundBackendComm->mutex, &shouldUnlockMutex, __FUNCTION__ );

    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT_MSG(
            "Finished queueing events to send asynchronously"
            "; disableSend: %s"
            "; serverTimeoutMilliseconds: %f (as integer: %"PRIu64")"
            "; serializedEvents [length: %"PRIu64"]:\n%.*s"
            , boolToString( disableSend )
            , serverTimeoutMilliseconds, (UInt64) serverTimeoutMillisecondsLong
            , (UInt64) serializedEvents.length, (int) serializedEvents.length, serializedEvents.begin );

    return resultCode;

    failure:
    goto finally;
}

ResultCode sendEventsToApmServer(
        bool disableSend
        , double serverTimeoutMilliseconds
        , const ConfigSnapshot* config
        , StringView serializedEvents )
{
    long serverTimeoutMillisecondsLong = (long) ceil( serverTimeoutMilliseconds );
    ELASTIC_APM_LOG_DEBUG(
            "Handling request to send events..."
            " disableSend: %s"
            "; serverTimeoutMilliseconds: %f (as integer: %"PRIu64")"
            "; serializedEvents [length: %"PRIu64"]:\n%.*s"
            , boolToString( disableSend )
            , serverTimeoutMilliseconds, (UInt64) serverTimeoutMillisecondsLong
            , (UInt64) serializedEvents.length, (int) serializedEvents.length, serializedEvents.begin );

    if ( ! config->asyncBackendComm )
    {
        ELASTIC_APM_LOG_DEBUG( "async_backend_comm (asyncBackendComm) configuration option is set to false - sending events synchronously" );
        return syncSendEventsToApmServer( disableSend
                                          , serverTimeoutMilliseconds
                                          , config
                                          , serializedEvents );
    }

    return enqueueEventsToSendToApmServer( disableSend, serverTimeoutMilliseconds, serializedEvents );
}