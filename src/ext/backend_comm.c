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
            resultCode = resultCurlFailure; \
            goto failure; \
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
        resultCode = resultFailure;
        goto failure;
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
            resultCode = resultFailure;
            goto failure;
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
        resultCode = resultFailure;
        goto failure;
    }
    ELASTIC_APM_CURL_EASY_SETOPT( curl, CURLOPT_USERAGENT, userAgent );

    snprintfRetVal = snprintf( url, urlBufferSize, "%s/intake/v2/events", config->serverUrl );
    if ( snprintfRetVal < 0 || snprintfRetVal >= authBufferSize )
    {
        ELASTIC_APM_LOG_ERROR( "Failed to build full URL to APM Server's intake API. snprintfRetVal: %d", snprintfRetVal );
        resultCode = resultFailure;
        goto failure;
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
        resultCode = resultFailure;
        goto failure;
    }

    long responseCode;
    curl_easy_getinfo( curl, CURLINFO_RESPONSE_CODE, &responseCode );
    ELASTIC_APM_LOG_DEBUG( "Sent events to APM Server. Response HTTP code: %ld. URL: `%s'.", responseCode, url );

    resultCode = resultSuccess;

    finally:
    if ( curl != NULL ) curl_easy_cleanup( curl );

    ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT_MSG( "resultCode: %s (%d)", resultCodeToString( resultCode ), resultCode );
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

static void freeBufferListNode( DataToSendNode** nodeOutPtr )
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

static void initBufferQueue( DataToSendQueue* dataQueue )
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
    freeBufferListNode( &newNode );
    goto finally;
}

static bool isDataToSendQueueEmpty( DataToSendQueue* dataQueue )
{
    ELASTIC_APM_ASSERT_VALID_PTR( dataQueue );

    return dataQueue->head.next == &( dataQueue->tail );
}

static const DataToSendNode* getFirstInDataToSendQueue( DataToSendQueue* dataQueue )
{
    ELASTIC_APM_ASSERT_VALID_PTR( dataQueue );

    return isDataToSendQueueEmpty( dataQueue ) ? NULL : dataQueue->head.next;
}

static void removeFirstFromDataToSendQueue( DataToSendQueue* dataQueue )
{
    ELASTIC_APM_ASSERT_VALID_PTR( dataQueue );
    ELASTIC_APM_ASSERT( ! isDataToSendQueueEmpty( dataQueue ), "" );

    DataToSendNode* nodeToRemove = dataQueue->head.next;
    DataToSendNode* newFirst = nodeToRemove->next;
    dataQueue->head.next = newFirst;
    newFirst->prev = &( dataQueue->head );

    freeBufferListNode( &nodeToRemove );
}

static void freeDataToSendQueue( DataToSendQueue* dataQueue )
{
    ELASTIC_APM_ASSERT_VALID_PTR( dataQueue );

    while ( ! isDataToSendQueueEmpty( dataQueue ) )
    {
        removeFirstFromDataToSendQueue( dataQueue );
    }
}

#define ELASTIC_APM_MAX_QUEUE_SIZE_IN_BYTES (10 * 1024 * 1024)

struct BackgroundBackendComm
{
    Mutex* mutex;
    ConditionVariable* condVar;
    Thread* thread;
    DataToSendQueue dataToSendQueue;
    size_t dataToSendQueueSize;
    size_t nextId;
    bool shouldExit;

};
typedef struct BackgroundBackendComm BackgroundBackendComm;

static BackgroundBackendComm* g_backgroundBackendComm = NULL;

static
void* backgroundBackendCommThreadFunc( void* arg )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "Starting ..." );

    ELASTIC_APM_ASSERT_VALID_PTR( arg );

    ResultCode resultCode;
    bool shouldUnlockMutex = false;
    BackgroundBackendComm* backgroundBackendComm = (BackgroundBackendComm*)arg;
    const ConfigSnapshot* config = getTracerCurrentConfigSnapshot( getGlobalTracer() );

    ELASTIC_APM_CALL_IF_FAILED_GOTO( lockMutex( backgroundBackendComm->mutex, __FUNCTION__ ) );
    shouldUnlockMutex = true;

    while ( true )
    {
        ELASTIC_APM_LOG_TRACE( "Loop iteration."
                               " dataToSendQueueSize: %"PRIu64
                               "; isDataToSendQueueEmpty: %s"
                               , (UInt64) backgroundBackendComm->dataToSendQueueSize
                               , boolToString( isDataToSendQueueEmpty( &( backgroundBackendComm->dataToSendQueue ) ) ) );

        while ( ! isDataToSendQueueEmpty( &( backgroundBackendComm->dataToSendQueue ) ) )
        {
            const DataToSendNode* nodeToSend = getFirstInDataToSendQueue( &( backgroundBackendComm->dataToSendQueue ) );

            ELASTIC_APM_LOG_DEBUG(
                    "First batch of events to send"
                    "; batch ID: %"PRIu64
                    "; batch size: %"PRIu64
                    "; total size of queued events: %"PRIu64
                    , (UInt64) nodeToSend->id
                    , (UInt64) nodeToSend->serializedEvents.length
                    , (UInt64) backgroundBackendComm->dataToSendQueueSize );

            resultCode = syncSendEventsToApmServer( nodeToSend->disableSend
                                                    , nodeToSend->serverTimeoutMilliseconds
                                                    , config
                                                    , nodeToSend->serializedEvents );
            if ( resultCode != resultSuccess )
            {
                ELASTIC_APM_LOG_ERROR(
                        "Failed to send batch of events - the batch will be dequeued and dropped"
                        "; batch ID: %"PRIu64
                        "; batch size: %"PRIu64
                        "; total size of queued events: %"PRIu64
                        , (UInt64) nodeToSend->id
                        , (UInt64) nodeToSend->serializedEvents.length
                        , (UInt64) backgroundBackendComm->dataToSendQueueSize );
            }

            backgroundBackendComm->dataToSendQueueSize -= nodeToSend->serializedEvents.length;
            removeFirstFromDataToSendQueue( &( backgroundBackendComm->dataToSendQueue ) );
        }

        if ( backgroundBackendComm->shouldExit )
        {
            break;
        }

        ELASTIC_APM_CALL_IF_FAILED_GOTO( waitConditionVariable( backgroundBackendComm->condVar, backgroundBackendComm->mutex, __FUNCTION__ ) );
    }

    finally:
    if ( shouldUnlockMutex )
    {
        unlockMutex( backgroundBackendComm->mutex, __FUNCTION__ );
    }
    ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT();
    return NULL;

    failure:
    goto finally;
}

static
void unwindBackgroundBackendComm( BackgroundBackendComm** backgroundBackendCommOutPtr )
{
    ELASTIC_APM_ASSERT_VALID_PTR( backgroundBackendCommOutPtr );

    BackgroundBackendComm* backgroundBackendComm = *backgroundBackendCommOutPtr;
    if ( backgroundBackendComm == NULL )
    {
        return;
    }

    if ( backgroundBackendComm->thread != NULL )
    {
        void* backgroundBackendCommThreadFuncRetVal = NULL;
        joinAndDeleteThread( &( backgroundBackendComm->thread ), &backgroundBackendCommThreadFuncRetVal, __FUNCTION__ );
    }

    if ( backgroundBackendComm->condVar != NULL )
    {
        deleteConditionVariable( &( backgroundBackendComm->condVar ) );
    }

    if ( backgroundBackendComm->mutex != NULL )
    {
        deleteMutex( &( backgroundBackendComm->mutex ) );
    }

    freeDataToSendQueue( &( backgroundBackendComm->dataToSendQueue ) );

    ELASTIC_APM_FREE_INSTANCE_AND_SET_TO_NULL( BackgroundBackendComm, *backgroundBackendCommOutPtr );
}

ResultCode startBackgroundBackendComm( const ConfigSnapshot* config )
{
    if ( ! config->asyncBackendComm )
    {
        ELASTIC_APM_LOG_DEBUG( "async_backend_comm (asyncBackendComm) configuration option is set to false - no need to start background backend comm" );
        return resultSuccess;
    }

    ResultCode resultCode;

    BackgroundBackendComm* backgroundBackendComm = NULL;

    ELASTIC_APM_MALLOC_INSTANCE_IF_FAILED_GOTO( BackgroundBackendComm, /* out */ backgroundBackendComm );
    backgroundBackendComm->shouldExit = false;
    initBufferQueue( &( backgroundBackendComm->dataToSendQueue ) );
    backgroundBackendComm->dataToSendQueueSize = 0;
    backgroundBackendComm->nextId = 1;
    backgroundBackendComm->condVar = NULL;
    backgroundBackendComm->mutex = NULL;
    backgroundBackendComm->thread = NULL;
    ELASTIC_APM_CALL_IF_FAILED_GOTO( newMutex( &( backgroundBackendComm->mutex ), /* dbgDesc */ "Background backend communications" ) );
    ELASTIC_APM_CALL_IF_FAILED_GOTO( newConditionVariable( &( backgroundBackendComm->condVar ), /* dbgDesc */ "Background backend communications" ) );
    ELASTIC_APM_CALL_IF_FAILED_GOTO(
            newThread( &( backgroundBackendComm->thread )
                       , &backgroundBackendCommThreadFunc
                       , /* threadFuncArg: */ backgroundBackendComm
                       , /* thread's dbgDesc */ "Background backend communications" ) );

    resultCode = resultSuccess;
    g_backgroundBackendComm = backgroundBackendComm;

    finally:
    return resultCode;

    failure:
    unwindBackgroundBackendComm( &backgroundBackendComm );
    goto finally;
}

void stopBackgroundBackendComm()
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "g_backgroundBackendComm %s NULL", g_backgroundBackendComm == NULL ? "==" : "!=" );

    if ( g_backgroundBackendComm == NULL )
    {
        return;
    }

    ResultCode resultCode;
    bool shouldUnlockMutex = false;

    ELASTIC_APM_CALL_IF_FAILED_GOTO( lockMutex( g_backgroundBackendComm->mutex, __FUNCTION__ ) );
    shouldUnlockMutex = true;

    g_backgroundBackendComm->shouldExit = true;
    ELASTIC_APM_CALL_IF_FAILED_GOTO( signalConditionVariable( g_backgroundBackendComm->condVar, __FUNCTION__ ) );

    finally:
    if ( shouldUnlockMutex )
    {
        unlockMutex( g_backgroundBackendComm->mutex, __FUNCTION__ );
    }
    unwindBackgroundBackendComm( &g_backgroundBackendComm );
    return;

    failure:
    goto finally;
}

static
ResultCode queueEventsToSendToApmServer(
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

    ELASTIC_APM_CALL_IF_FAILED_GOTO( lockMutex( g_backgroundBackendComm->mutex, __FUNCTION__ ) );
    shouldUnlockMutex = true;

    if ( g_backgroundBackendComm->dataToSendQueueSize >= ELASTIC_APM_MAX_QUEUE_SIZE_IN_BYTES )
    {
        ELASTIC_APM_LOG_ERROR(
                "Already queued events are above max queue size - dropping these events"
                "; size of already queued events: %"PRIu64
                , (UInt64) g_backgroundBackendComm->dataToSendQueueSize );
        resultCode = resultFailure;
        goto failure;
    }

    const UInt64 id = g_backgroundBackendComm->nextId;
    ELASTIC_APM_CALL_IF_FAILED_GOTO(
            addCopyToDataToSendQueue( &( g_backgroundBackendComm->dataToSendQueue )
                                      , id
                                      , disableSend
                                      , serverTimeoutMilliseconds
                                      , serializedEvents ) );

    ++g_backgroundBackendComm->nextId;
    g_backgroundBackendComm->dataToSendQueueSize += serializedEvents.length;

    ELASTIC_APM_LOG_DEBUG(
            "Queued a batch of events"
            "; batch ID: %"PRIu64
            "; batch size: %"PRIu64
            "; total size of queued events: %"PRIu64
            , (UInt64) id
            , (UInt64) serializedEvents.length
            , (UInt64) g_backgroundBackendComm->dataToSendQueueSize );

    ELASTIC_APM_CALL_IF_FAILED_GOTO( signalConditionVariable( g_backgroundBackendComm->condVar, __FUNCTION__ ) );

    resultCode = resultSuccess;

    finally:
    if ( shouldUnlockMutex )
    {
        unlockMutex( g_backgroundBackendComm->mutex, __FUNCTION__ );
    }

    ELASTIC_APM_LOG_WITH_LEVEL(
            resultCode == resultSuccess ? logLevel_debug : logLevel_error
            , "Queueing events to send asynchronously finished - result code: %s (%d)"
            "; disableSend: %s"
            "; serverTimeoutMilliseconds: %f (as integer: %"PRIu64")"
            "; serializedEvents [length: %"PRIu64"]:\n%.*s"
            , resultCodeToString( resultCode ), resultCode
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

    return queueEventsToSendToApmServer( disableSend, serverTimeoutMilliseconds, serializedEvents );
}