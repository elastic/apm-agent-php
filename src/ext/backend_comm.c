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
#include "util_for_PHP.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_BACKEND_COMM


struct StringBuffer
{
    char* begin;
    size_t size;
};
typedef struct StringBuffer StringBuffer;

static ResultCode dupMallocStringView( StringView src, StringBuffer* dst )
{
    ELASTIC_APM_ASSERT_VALID_PTR( src.begin );
    ELASTIC_APM_ASSERT_VALID_PTR( dst );
    ELASTIC_APM_ASSERT_PTR_IS_NULL( dst->begin );
    ELASTIC_APM_ASSERT( dst->size == 0, "" );

    ResultCode resultCode;
    char* memBlockForDup = NULL;
    const size_t memBlockForDupSize = src.length + 1;

    // +1 for terminating '\0'
    ELASTIC_APM_MALLOC_IF_FAILED_GOTO( char, memBlockForDupSize, /* out */ memBlockForDup );

    resultCode = resultSuccess;

    memcpy( memBlockForDup, src.begin, src.length );
    memBlockForDup[ memBlockForDupSize - 1 ] = '\0';

    dst->begin = memBlockForDup;
    memBlockForDup = NULL;
    dst->size = memBlockForDupSize;

    finally:
    return resultCode;

    failure:
    ELASTIC_APM_FREE_AND_SET_TO_NULL( char, memBlockForDupSize, /* out */ memBlockForDup );
    goto finally;
}

static void freeMallocedStringBuffer( /* in,out */ StringBuffer* strBuf )
{
    ELASTIC_APM_ASSERT_VALID_PTR( strBuf );
    if ( strBuf->begin != NULL )
    {
        ELASTIC_APM_FREE_AND_SET_TO_NULL( char, strBuf->size, /* in,out */ strBuf->begin );
        ELASTIC_APM_ZERO_STRUCT( strBuf );
    }
    else
    {
        ELASTIC_APM_ASSERT( strBuf->size == 0, "" );
    }
}

StringView viewStringBuffer( StringBuffer strBuf )
{
    // -1 since terminating '\0' is counted in buffer's size but not in string's length
    return (StringView)
    {
        .begin = strBuf.begin,
        .length = (strBuf.begin == NULL) ? 0 : (strBuf.size - 1)
    };
}

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

#define ELASTIC_APM_CURL_EASY_SETOPT( curlHandle, curlOptionId, ... ) \
    do { \
        CURLcode curl_easy_setopt_ret_val = curl_easy_setopt( curlHandle, curlOptionId, __VA_ARGS__ ); \
        if ( curl_easy_setopt_ret_val != CURLE_OK ) \
        { \
            ELASTIC_APM_LOG_ERROR( "Failed to set cUrl option. curlOptionId: %d.", curlOptionId ); \
            ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE_EX( resultCurlFailure ); \
        } \
    } while ( false ) \
    /**/

ResultCode addToCurlStringList( /* in,out */ struct curl_slist** pList, const char* strToAdd )
{
    ELASTIC_APM_ASSERT_VALID_PTR( pList );
    ELASTIC_APM_ASSERT_VALID_PTR( strToAdd );

    struct curl_slist* newList = curl_slist_append( *pList, strToAdd );
    if ( newList == NULL )
    {
        ELASTIC_APM_LOG_ERROR( "Failed to curl_slist_append(); strToAdd: %s", strToAdd );
        return resultCurlFailure;
    }

    *pList = newList;
    return resultSuccess;
}

struct ConnectionData
{
    CURL* curlHandle;
    struct curl_slist* requestHeaders;
};
typedef struct ConnectionData ConnectionData;
ConnectionData g_connectionData = { .curlHandle = NULL, .requestHeaders = NULL };

void cleanupConnectionData( ConnectionData* connectionData )
{
    ELASTIC_APM_ASSERT_VALID_PTR( connectionData );

    if ( connectionData->requestHeaders != NULL )
    {
        curl_slist_free_all( connectionData->requestHeaders );
        connectionData->requestHeaders = NULL;
    }

    if ( connectionData->curlHandle != NULL )
    {
        curl_easy_cleanup( connectionData->curlHandle );
        connectionData->curlHandle = NULL;
    }
}

ResultCode initConnectionData( const ConfigSnapshot* config, ConnectionData* connectionData, StringView userAgentHttpHeader )
{
    ResultCode resultCode;
    enum { authBufferSize = 256 };
    char auth[authBufferSize];
    const char* authKind = NULL;
    const char* authValue = NULL;
    int snprintfRetVal;
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTIC_APM_ASSERT_VALID_PTR( connectionData );
    ELASTIC_APM_ASSERT( connectionData->curlHandle == NULL, "" );
    ELASTIC_APM_ASSERT( connectionData->requestHeaders == NULL, "" );

    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG(
            "config: { serverUrl: %s, disableSend: %s, serverTimeout: %s }"
            "; userAgentHttpHeader: `%s'"
            , config->serverUrl
            , boolToString( config->disableSend )
            , streamDuration( config->serverTimeout, &txtOutStream )
            , streamStringView( userAgentHttpHeader, &txtOutStream ) );
    textOutputStreamRewind( &txtOutStream );

    connectionData->curlHandle = curl_easy_init();
    if ( connectionData->curlHandle == NULL )
    {
        ELASTIC_APM_LOG_ERROR( "curl_easy_init() returned NULL" );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE_EX( resultCurlFailure );
    }

    ELASTIC_APM_CURL_EASY_SETOPT( connectionData->curlHandle, CURLOPT_WRITEFUNCTION, logResponse );

    if ( config->serverTimeout.valueInUnits == 0 )
    {
        ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "Timeout is disabled. %s (serverTimeout): %s"
                                                  , ELASTIC_APM_CFG_OPT_NAME_SERVER_TIMEOUT, streamDuration( config->serverTimeout, &txtOutStream ) );
        textOutputStreamRewind( &txtOutStream );
    }
    else
    {
        long serverTimeoutInMilliseconds = (long)durationToMilliseconds( config->serverTimeout );
        ELASTIC_APM_CURL_EASY_SETOPT( connectionData->curlHandle, CURLOPT_TIMEOUT_MS, serverTimeoutInMilliseconds );
    }

    if ( ! config->verifyServerCert )
    {
        ELASTIC_APM_LOG_DEBUG( "verify_server_cert configuration option is set to false"
                               " - disabling SSL/TLS certificate verification for communication with APM Server..." );
        ELASTIC_APM_CURL_EASY_SETOPT( connectionData->curlHandle, CURLOPT_SSL_VERIFYPEER, 0L );
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
        ELASTIC_APM_CALL_IF_FAILED_GOTO( addToCurlStringList( /* in,out */ &connectionData->requestHeaders, auth ) );
    }
    ELASTIC_APM_CALL_IF_FAILED_GOTO( addToCurlStringList( /* in,out */ &connectionData->requestHeaders, "Content-Type: application/x-ndjson" ) );
    ELASTIC_APM_CURL_EASY_SETOPT( connectionData->curlHandle, CURLOPT_HTTPHEADER, connectionData->requestHeaders );

    ELASTIC_APM_CURL_EASY_SETOPT( connectionData->curlHandle, CURLOPT_USERAGENT, userAgentHttpHeader );

    resultCode = resultSuccess;
    finally:
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    return resultCode;

    failure:
    goto finally;
}

ResultCode syncSendEventsToApmServerWithConn( const ConfigSnapshot* config, ConnectionData* connectionData, StringView serializedEvents )
{
    ResultCode resultCode;
    CURLcode curlResult;
    enum { urlBufferSize = 256 };
    char url[urlBufferSize];
    int snprintfRetVal;

    ELASTIC_APM_ASSERT_VALID_PTR( connectionData );
    ELASTIC_APM_ASSERT( connectionData->curlHandle != NULL, "" );

    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    ELASTIC_APM_CURL_EASY_SETOPT( connectionData->curlHandle, CURLOPT_POST, 1L );
    ELASTIC_APM_CURL_EASY_SETOPT( connectionData->curlHandle, CURLOPT_POSTFIELDS, serializedEvents.begin );
    ELASTIC_APM_CURL_EASY_SETOPT( connectionData->curlHandle, CURLOPT_POSTFIELDSIZE, serializedEvents.length );

    snprintfRetVal = snprintf( url, urlBufferSize, "%s/intake/v2/events", config->serverUrl );
    if ( snprintfRetVal < 0 || snprintfRetVal >= urlBufferSize )
    {
        ELASTIC_APM_LOG_ERROR( "Failed to build full URL to APM Server's intake API. snprintfRetVal: %d", snprintfRetVal );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }
    ELASTIC_APM_CURL_EASY_SETOPT( connectionData->curlHandle, CURLOPT_URL, url );

    curlResult = curl_easy_perform( connectionData->curlHandle );
    if ( curlResult != CURLE_OK )
    {
        char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
        TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
        ELASTIC_APM_LOG_ERROR(
                "Sending events to APM Server failed."
                " URL: `%s'."
                " Error message: `%s'."
                " Current process command line: `%s'"
                , url
                , curl_easy_strerror( curlResult )
                , streamCurrentProcessCommandLine( &txtOutStream ) );
        ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
    }

    long responseCode;
    curl_easy_getinfo( connectionData->curlHandle, CURLINFO_RESPONSE_CODE, &responseCode );
    ELASTIC_APM_LOG_DEBUG( "Sent events to APM Server. Response HTTP code: %ld. URL: `%s'.", responseCode, url );

    resultCode = resultSuccess;
    finally:
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    return resultCode;

    failure:
    goto finally;
}

ResultCode syncSendEventsToApmServer( const ConfigSnapshot* config, StringView userAgentHttpHeader, StringView serializedEvents )
{
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
    ResultCode resultCode;
    ConnectionData* connectionData = &g_connectionData;

    ELASTIC_APM_ASSERT_VALID_PTR( connectionData );

    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG(
            "Sending events to APM Server..."
            "; config: { serverUrl: %s, disableSend: %s, serverTimeout: %s }"
            "; userAgentHttpHeader: `%s'"
            "; serializedEvents [length: %"PRIu64"]:\n%.*s"
            , config->serverUrl
            , boolToString( config->disableSend )
            , streamDuration( config->serverTimeout, &txtOutStream )
            , streamStringView( userAgentHttpHeader, &txtOutStream )
            , (UInt64) serializedEvents.length, (int) serializedEvents.length, serializedEvents.begin );
    textOutputStreamRewind( &txtOutStream );

    if ( config->disableSend )
    {
        ELASTIC_APM_LOG_DEBUG( "disable_send (disableSend) configuration option is set to true - discarding events instead of sending" );
        ELASTIC_APM_CALL_EARLY_GOTO_FINALLY_WITH_SUCCESS();
    }

    if ( connectionData->curlHandle == NULL )
    {
        ELASTIC_APM_CALL_IF_FAILED_GOTO( initConnectionData( config, connectionData, userAgentHttpHeader ) );
    }

    ELASTIC_APM_CALL_IF_FAILED_GOTO( syncSendEventsToApmServerWithConn( config, connectionData, serializedEvents ) );

    resultCode = resultSuccess;
    finally:
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    return resultCode;

    failure:
    cleanupConnectionData( connectionData );
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

    StringBuffer userAgentHttpHeader;
    StringBuffer serializedEvents;
};

static void freeDataToSendNode( DataToSendNode** nodeOutPtr )
{
    ELASTIC_APM_ASSERT_VALID_IN_PTR_TO_PTR( nodeOutPtr );

    freeMallocedStringBuffer( /* in,out */ &( (*nodeOutPtr)->userAgentHttpHeader ) );
    freeMallocedStringBuffer( /* in,out */ &( (*nodeOutPtr)->serializedEvents ) );
    ELASTIC_APM_ZERO_STRUCT( *nodeOutPtr );
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
                                            , StringView userAgentHttpHeader
                                            , StringView serializedEvents )
{
    ELASTIC_APM_ASSERT_VALID_PTR( dataQueue );

    ResultCode resultCode;
    DataToSendNode* newNode = NULL;

    ELASTIC_APM_MALLOC_INSTANCE_IF_FAILED_GOTO( DataToSendNode, /* out */ newNode );
    ELASTIC_APM_ZERO_STRUCT( newNode );

    ELASTIC_APM_CALL_IF_FAILED_GOTO( dupMallocStringView( userAgentHttpHeader, /* out */ &( newNode->userAgentHttpHeader ) ) );
    ELASTIC_APM_CALL_IF_FAILED_GOTO( dupMallocStringView( serializedEvents, /* out */ &( newNode->serializedEvents ) ) );

    resultCode = resultSuccess;

    newNode->id = id;

    newNode->next = &( dataQueue->tail );
    newNode->prev = dataQueue->tail.prev;
    dataQueue->tail.prev->next = newNode;
    dataQueue->tail.prev = newNode;

    finally:
    return resultCode;

    failure:
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
    // -1 since terminating '\0' is counted in buffer's size but not in string's length
    size_t firstNodeDataSize = firstNode->serializedEvents.size - 1;
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
    Mutex* mutex;
    ConditionVariable* condVar;
    Thread* thread;
    DataToSendQueue dataToSendQueue;
    size_t dataToSendTotalSize;
    size_t nextEventsBatchId;
    bool shouldExit;
    TimeSpec shouldExitBy;
};
typedef struct BackgroundBackendComm BackgroundBackendComm;

struct BackgroundBackendCommSharedStateSnapshot
{
    const DataToSendNode* firstDataToSendNode;
    size_t dataToSendTotalSize;
    bool shouldExit;
    TimeSpec shouldExitBy;
};
typedef struct BackgroundBackendCommSharedStateSnapshot BackgroundBackendCommSharedStateSnapshot;

static inline bool isDataToSendQueueEmptyInSnapshot( const BackgroundBackendCommSharedStateSnapshot* sharedStateSnapshot )
{
    return sharedStateSnapshot->firstDataToSendNode == NULL;
}

String streamSharedStateSnapshot( const BackgroundBackendCommSharedStateSnapshot* sharedStateSnapshot, TextOutputStream* txtOutStream )
{
    StringView serializedEvents = { 0 };
    if ( ! isDataToSendQueueEmptyInSnapshot( sharedStateSnapshot ) )
    {
        serializedEvents = viewStringBuffer( sharedStateSnapshot->firstDataToSendNode->serializedEvents );
    }

    streamPrintf(
            txtOutStream
            ,"{"
             "total size of queued events: %"PRIu64
             ", firstDataToSendNode %s NULL"
             " (serializedEvents.length: %"PRIu64 ")"
             ", shouldExit: %s"
             ", shouldExitBy: %s"
             "}"
            , (UInt64) sharedStateSnapshot->dataToSendTotalSize
            , sharedStateSnapshot->firstDataToSendNode == NULL ? "==" : "!="
            , (UInt64) serializedEvents.length
            , boolToString( sharedStateSnapshot->shouldExit )
            , sharedStateSnapshot->shouldExit ? streamUtcTimeSpecAsLocal( &(sharedStateSnapshot->shouldExitBy), txtOutStream ) : "N/A"
    );
}

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

void backgroundBackendCommThreadFunc_underLockCopySharedStateToSnapshot(
        BackgroundBackendComm* backgroundBackendComm
        , /* out */ BackgroundBackendCommSharedStateSnapshot* sharedStateSnapshot
)
{
    ELASTIC_APM_ASSERT_VALID_PTR( sharedStateSnapshot );

    sharedStateSnapshot->firstDataToSendNode = getFirstNodeInDataToSendQueue( &( backgroundBackendComm->dataToSendQueue ) );
    sharedStateSnapshot->dataToSendTotalSize = backgroundBackendComm->dataToSendTotalSize;
    sharedStateSnapshot->shouldExit = backgroundBackendComm->shouldExit;
    sharedStateSnapshot->shouldExitBy = backgroundBackendComm->shouldExitBy;
}

static inline bool areEqualSharedSnapshots( const BackgroundBackendCommSharedStateSnapshot* val1, const BackgroundBackendCommSharedStateSnapshot* val2 )
{
    if ( isDataToSendQueueEmptyInSnapshot( val1 ) != isDataToSendQueueEmptyInSnapshot( val2 ) )
    {
        return false;
    }

    if ( val1->shouldExit != val2->shouldExit )
    {
        return false;
    }

    if ( val1->shouldExit )
    {
        if ( compareAbsTimeSpecs( &( val1->shouldExitBy ), &( val2->shouldExitBy ) ) != 0 )
        {
            return false;
        }
    }

    return true;
}

ResultCode backgroundBackendCommThreadFunc_getSharedStateSnapshot(
        BackgroundBackendComm* backgroundBackendComm
        , /* out */ BackgroundBackendCommSharedStateSnapshot* sharedStateSnapshot
)
{
    ELASTIC_APM_ASSERT_VALID_PTR( sharedStateSnapshot );

    ELASTIC_APM_BACKGROUND_BACKEND_COMM_DO_UNDER_LOCK_PROLOG()

    backgroundBackendCommThreadFunc_underLockCopySharedStateToSnapshot( backgroundBackendComm, /* out */ sharedStateSnapshot );

    ELASTIC_APM_BACKGROUND_BACKEND_COMM_DO_UNDER_LOCK_EPILOG()
}

ResultCode backgroundBackendCommThreadFunc_shouldBreakLoop(
        const BackgroundBackendCommSharedStateSnapshot* sharedStateSnapshot
        , bool* shouldBreakLoop
)
{
    ResultCode resultCode;

    if ( sharedStateSnapshot->shouldExit )
    {
        if ( isDataToSendQueueEmptyInSnapshot( sharedStateSnapshot ) )
        {
            *shouldBreakLoop = true;
            goto success;
        }

        TimeSpec now;
        ELASTIC_APM_CALL_IF_FAILED_GOTO( getCurrentAbsTimeSpec( /* out */ &now ) );

        if ( compareAbsTimeSpecs( &sharedStateSnapshot->shouldExitBy, &now ) < 0 )
        {
            *shouldBreakLoop = true;
            goto success;
        }
    }

    *shouldBreakLoop = false;

    success:
    resultCode = resultSuccess;

    finally:
    return resultCode;

    failure:
    goto finally;
}

ResultCode backgroundBackendCommThreadFunc_removeFirstEventsBatchAndUpdateSnapshot(
        BackgroundBackendComm* backgroundBackendComm
        , /* out */ BackgroundBackendCommSharedStateSnapshot* sharedStateSnapshot
)
{
    ELASTIC_APM_BACKGROUND_BACKEND_COMM_DO_UNDER_LOCK_PROLOG()

    size_t firstNodeDataSize = removeFirstNodeInDataToSendQueue( &( backgroundBackendComm->dataToSendQueue ) );
    backgroundBackendComm->dataToSendTotalSize -= firstNodeDataSize;

    backgroundBackendCommThreadFunc_underLockCopySharedStateToSnapshot( backgroundBackendComm, /* out */ sharedStateSnapshot );

    ELASTIC_APM_BACKGROUND_BACKEND_COMM_DO_UNDER_LOCK_EPILOG()
}

ResultCode backgroundBackendCommThreadFunc_waitForChangesInSharedState(
        BackgroundBackendComm* backgroundBackendComm
        , /* in,out */ BackgroundBackendCommSharedStateSnapshot* sharedStateSnapshot
)
{
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTIC_APM_BACKGROUND_BACKEND_COMM_DO_UNDER_LOCK_PROLOG()

    BackgroundBackendCommSharedStateSnapshot localSharedStateSnapshot;
    backgroundBackendCommThreadFunc_underLockCopySharedStateToSnapshot( backgroundBackendComm, /* out */ &localSharedStateSnapshot );
    if ( areEqualSharedSnapshots( sharedStateSnapshot, &localSharedStateSnapshot ) )
    {
        ELASTIC_APM_LOG_DEBUG( "Shared state is the same - we need to wait; shared state snapshots: before lock: %s, after lock: %s"
                               , streamSharedStateSnapshot( sharedStateSnapshot, &txtOutStream )
                               , streamSharedStateSnapshot( &localSharedStateSnapshot, &txtOutStream ) );
        textOutputStreamRewind( &txtOutStream );
        ELASTIC_APM_CALL_IF_FAILED_GOTO( waitConditionVariable( backgroundBackendComm->condVar, backgroundBackendComm->mutex, __FUNCTION__ ) );
        backgroundBackendCommThreadFunc_underLockCopySharedStateToSnapshot( backgroundBackendComm, /* out */ sharedStateSnapshot );
        ELASTIC_APM_LOG_DEBUG( "Waiting exited; shared state snapshots: after lock: %s, after wait: %s"
                               , streamSharedStateSnapshot( &localSharedStateSnapshot, &txtOutStream )
                               , streamSharedStateSnapshot( sharedStateSnapshot, &txtOutStream ) );
    }
    else
    {
        ELASTIC_APM_LOG_DEBUG( "Shared state is not the same - there is no need to wait; shared state snapshots: before lock: %s, after lock: %s"
                               , streamSharedStateSnapshot( sharedStateSnapshot, &txtOutStream )
                               , streamSharedStateSnapshot( &localSharedStateSnapshot, &txtOutStream ) );
        *sharedStateSnapshot = localSharedStateSnapshot;
    }

    ELASTIC_APM_BACKGROUND_BACKEND_COMM_DO_UNDER_LOCK_EPILOG()
}

ResultCode backgroundBackendCommThreadFunc_sendFirstEventsBatch(
        const ConfigSnapshot* config
        , const BackgroundBackendCommSharedStateSnapshot* sharedStateSnapshot )
{
    // This function is called only when data-queue-to-send is not empty
    // so firstDataToSendNode is not NULL
    StringView serializedEvents = viewStringBuffer( sharedStateSnapshot->firstDataToSendNode->serializedEvents );

    ELASTIC_APM_LOG_DEBUG(
            "About to send batch of events"
            "; batch ID: %"PRIu64
            "; batch size: %"PRIu64
            "; total size of queued events: %"PRIu64
            , (UInt64) sharedStateSnapshot->firstDataToSendNode->id
            , (UInt64) serializedEvents.length
            , (UInt64) sharedStateSnapshot->dataToSendTotalSize );

    ResultCode resultCode;

    resultCode = syncSendEventsToApmServer( config
                                            , viewStringBuffer( sharedStateSnapshot->firstDataToSendNode->userAgentHttpHeader )
                                            , serializedEvents );
    // If we failed to send the currently first batch we return success nevertheless
    // it means that this batch will be removed, and we will continue on to sending the rest of the queued events
    if ( resultCode != resultSuccess )
    {
        ELASTIC_APM_LOG_ERROR(
                "Failed to send batch of events - the batch will be dequeued and dropped"
                "; batch ID: %"PRIu64
                "; batch size: %"PRIu64
                "; total size of queued events: %"PRIu64
                , (UInt64) sharedStateSnapshot->firstDataToSendNode->id
                , (UInt64) serializedEvents.length
                , (UInt64) sharedStateSnapshot->dataToSendTotalSize );
    }

    return resultSuccess;
}

#undef ELASTIC_APM_BACKGROUND_BACKEND_COMM_DO_UNDER_LOCK_EPILOG
#undef ELASTIC_APM_BACKGROUND_BACKEND_COMM_DO_UNDER_LOCK_PROLOG

void backgroundBackendCommThreadFunc_logSharedStateSnapshot( const BackgroundBackendCommSharedStateSnapshot* sharedStateSnapshot )
{
    StringView serializedEvents = { 0 };
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTIC_APM_LOG_TRACE( "Shared state snapshot: %s", streamSharedStateSnapshot( sharedStateSnapshot, &txtOutStream ) );

    if ( ! isDataToSendQueueEmptyInSnapshot( sharedStateSnapshot ) )
    {
        serializedEvents = viewStringBuffer( sharedStateSnapshot->firstDataToSendNode->serializedEvents );
    }

    ELASTIC_APM_ASSERT( (sharedStateSnapshot->dataToSendTotalSize == 0) == ( sharedStateSnapshot->firstDataToSendNode == NULL )
                        , "dataToSendTotalSize: %"PRIu64 ", firstDataToSendNode: %p (serializedEvents.length: %"PRIu64 ")"
                        , (UInt64) sharedStateSnapshot->dataToSendTotalSize
                        , sharedStateSnapshot->firstDataToSendNode
                        , (UInt64) serializedEvents.length );
}

void* backgroundBackendCommThreadFunc( void* arg )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    ELASTIC_APM_ASSERT_VALID_PTR( arg );

    ResultCode resultCode;
    BackgroundBackendComm* backgroundBackendComm = (BackgroundBackendComm*)arg;
    const ConfigSnapshot* config = getTracerCurrentConfigSnapshot( getGlobalTracer() );

    BackgroundBackendCommSharedStateSnapshot sharedStateSnapshot;
    ELASTIC_APM_CALL_IF_FAILED_GOTO( backgroundBackendCommThreadFunc_getSharedStateSnapshot( backgroundBackendComm, /* out */ &sharedStateSnapshot ) );
    while ( true )
    {
        backgroundBackendCommThreadFunc_logSharedStateSnapshot( &sharedStateSnapshot );

        bool shouldBreakLoop;
        ELASTIC_APM_CALL_IF_FAILED_GOTO( backgroundBackendCommThreadFunc_shouldBreakLoop( /* in */ &sharedStateSnapshot, /* out */ &shouldBreakLoop ) );
        if ( shouldBreakLoop )
        {
            break;
        }

        if ( isDataToSendQueueEmptyInSnapshot( &sharedStateSnapshot ) )
        {
            ELASTIC_APM_CALL_IF_FAILED_GOTO( backgroundBackendCommThreadFunc_waitForChangesInSharedState( backgroundBackendComm, /* out */ &sharedStateSnapshot ) );
            continue;
        }

        ELASTIC_APM_CALL_IF_FAILED_GOTO( backgroundBackendCommThreadFunc_sendFirstEventsBatch( config, /* in */ &sharedStateSnapshot ) );
        ELASTIC_APM_CALL_IF_FAILED_GOTO( backgroundBackendCommThreadFunc_removeFirstEventsBatchAndUpdateSnapshot( backgroundBackendComm, /* out */ &sharedStateSnapshot ) );
    }

    resultCode = resultSuccess;

    finally:
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    return NULL;

    failure:
    goto finally;
}

ResultCode unwindBackgroundBackendComm( BackgroundBackendComm** backgroundBackendCommOutPtr, const TimeSpec* timeoutAbsUtc, bool isCreatedByThisProcess )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "isCreatedByThisProcess: %s", boolToString( isCreatedByThisProcess ) );

    ELASTIC_APM_ASSERT_VALID_PTR( backgroundBackendCommOutPtr );
    // ELASTIC_APM_ASSERT_VALID_PTR( timeoutAbsUtc ); <- timeoutAbsUtc can be NULL

    ResultCode resultCode;

    BackgroundBackendComm* backgroundBackendComm = *backgroundBackendCommOutPtr;
    if ( backgroundBackendComm == NULL )
    {
        resultCode = resultSuccess;
        goto finally;
    }

    if ( ! isCreatedByThisProcess )
    {
        ELASTIC_APM_LOG_DEBUG( "Deallocating memory related to background communication data structures inherited from parent process after fork"
                               " without actually properly destroying synchronization primitives since it's impossible to do in child process"
                               "; parent PID: %d"
                               , (int)getParentProcessId() );
    }

    if ( backgroundBackendComm->thread != NULL )
    {
        void* backgroundBackendCommThreadFuncRetVal = NULL;
        bool hasTimedOut;
        ELASTIC_APM_CALL_IF_FAILED_GOTO(
                timedJoinAndDeleteThread( &( backgroundBackendComm->thread ), &backgroundBackendCommThreadFuncRetVal, timeoutAbsUtc, isCreatedByThisProcess, &hasTimedOut, __FUNCTION__ ) );
        if ( hasTimedOut )
        {
            ELASTIC_APM_LOG_ERROR( "Join to thread for background backend communications timed out - skipping the rest of cleanup and exiting" );
            ELASTIC_APM_SET_RESULT_CODE_AND_GOTO_FAILURE();
        }
    }

    if ( backgroundBackendComm->condVar != NULL )
    {
        ELASTIC_APM_CALL_IF_FAILED_GOTO( deleteConditionVariable( &( backgroundBackendComm->condVar ), isCreatedByThisProcess ) );
    }

    if ( backgroundBackendComm->mutex != NULL )
    {
        ELASTIC_APM_CALL_IF_FAILED_GOTO( deleteMutex( &( backgroundBackendComm->mutex ) ) );
    }

    resultCode = resultSuccess;
    freeDataToSendQueue( &( backgroundBackendComm->dataToSendQueue ) );
    ELASTIC_APM_FREE_INSTANCE_AND_SET_TO_NULL( BackgroundBackendComm, *backgroundBackendCommOutPtr );

    finally:
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    return resultCode;

    failure:
    goto finally;
}

static BackgroundBackendComm* g_backgroundBackendComm = NULL;

static bool deriveAsyncBackendComm( const ConfigSnapshot* config, String* dbgReason )
{
    if ( config->asyncBackendComm.isSet )
    {
        *dbgReason = config->asyncBackendComm.value ? "explicitly set to true" : "explicitly set to false";
        return config->asyncBackendComm.value;
    }

    if ( isPhpRunningAsCliScript() )
    {
        *dbgReason = "implicitly set to false because PHP is running as CLI script";
        return false;
    }

    *dbgReason = "implicitly set to true";
    return true;
}

ResultCode newBackgroundBackendComm( const ConfigSnapshot* config, BackgroundBackendComm** backgroundBackendCommOut )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    ResultCode resultCode;
    BackgroundBackendComm* backgroundBackendComm = NULL;

    ELASTIC_APM_MALLOC_INSTANCE_IF_FAILED_GOTO( BackgroundBackendComm, /* out */ backgroundBackendComm );
    backgroundBackendComm->condVar = NULL;
    backgroundBackendComm->mutex = NULL;
    backgroundBackendComm->thread = NULL;
    initDataToSendQueue( &( backgroundBackendComm->dataToSendQueue ) );
    backgroundBackendComm->dataToSendTotalSize = 0;
    backgroundBackendComm->nextEventsBatchId = 1;
    backgroundBackendComm->shouldExit = false;
    ELASTIC_APM_CALL_IF_FAILED_GOTO( newMutex( &( backgroundBackendComm->mutex ), /* dbgDesc */ "Background backend communications" ) );
    ELASTIC_APM_CALL_IF_FAILED_GOTO( newConditionVariable( &( backgroundBackendComm->condVar ), /* dbgDesc */ "Background backend communications" ) );

    resultCode = newThread( &( backgroundBackendComm->thread )
                            , &backgroundBackendCommThreadFunc
                            , /* threadFuncArg: */ backgroundBackendComm
                            , /* thread's dbgDesc */ "Background backend communications" );
    if ( resultCode == resultSuccess )
    {
        ELASTIC_APM_LOG_DEBUG( "Started thread for background backend communications; thread ID: %"PRIu64, getThreadId( backgroundBackendComm->thread ) );
    }

    resultCode = resultSuccess;
    *backgroundBackendCommOut = backgroundBackendComm;

    finally:
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    return resultCode;

    failure:
    unwindBackgroundBackendComm( &backgroundBackendComm, /* timeoutAbsUtc: */ NULL, /* isCreatedByThisProcess */ true );
    goto finally;
}

ResultCode backgroundBackendCommEnsureInited( const ConfigSnapshot* config )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    ResultCode resultCode;

    if ( g_backgroundBackendComm == NULL )
    {
        ELASTIC_APM_CALL_IF_FAILED_GOTO( newBackgroundBackendComm( config, &g_backgroundBackendComm ) );
    }

    resultCode = resultSuccess;

    finally:
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    return resultCode;

    failure:
    goto finally;
}

static
ResultCode signalBackgroundBackendCommThreadToExit( const ConfigSnapshot* config
                                                    , BackgroundBackendComm* backgroundBackendComm
                                                    , /* out */ TimeSpec* shouldExitBy )
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY();

    ResultCode resultCode;
    bool shouldUnlockMutex = false;
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTIC_APM_CALL_IF_FAILED_GOTO( lockMutex( backgroundBackendComm->mutex, &shouldUnlockMutex, __FUNCTION__ ) );

    backgroundBackendComm->shouldExit = true;

    ELASTIC_APM_CALL_IF_FAILED_GOTO( getCurrentAbsTimeSpec( /* out */ shouldExitBy ) );
    addDelayToAbsTimeSpec( /* in, out */ shouldExitBy, (long)durationToMilliseconds( config->serverTimeout ) * ELASTIC_APM_NUMBER_OF_NANOSECONDS_IN_MILLISECOND );
    backgroundBackendComm->shouldExitBy = *shouldExitBy;
    ELASTIC_APM_CALL_IF_FAILED_GOTO( signalConditionVariable( backgroundBackendComm->condVar, __FUNCTION__ ) );

    resultCode = resultSuccess;
    finally:
    unlockMutex( backgroundBackendComm->mutex, &shouldUnlockMutex, __FUNCTION__ );
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT_MSG(
            "shouldExitBy: %s, serverTimeout: %s"
            , streamUtcTimeSpecAsLocal( shouldExitBy, &txtOutStream ), streamDuration( config->serverTimeout, &txtOutStream ) );
    textOutputStreamRewind( &txtOutStream );
    return resultCode;

    failure:
    goto finally;
}

void backgroundBackendCommOnModuleShutdown( const ConfigSnapshot* config )
{
    BackgroundBackendComm* backgroundBackendComm = g_backgroundBackendComm;

    if ( backgroundBackendComm == NULL )
    {
        return;
    }

    ResultCode resultCode;
    TimeSpec shouldExitBy;

    ELASTIC_APM_CALL_IF_FAILED_GOTO( signalBackgroundBackendCommThreadToExit( config, backgroundBackendComm, /* out */ &shouldExitBy ) );
    ELASTIC_APM_CALL_IF_FAILED_GOTO( unwindBackgroundBackendComm( &backgroundBackendComm, &shouldExitBy, /* isCreatedByThisProcess */ true ) );

    resultCode = resultSuccess;
    finally:
    cleanupConnectionData( &g_connectionData );
    g_backgroundBackendComm = NULL;
    return;

    failure:
    goto finally;
}

static
ResultCode enqueueEventsToSendToApmServer( StringView userAgentHttpHeader, StringView serializedEvents )
{
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTIC_APM_LOG_DEBUG(
            "Queueing events to send asynchronously..."
            "; userAgentHttpHeader [length: %"PRIu64"]: `%.*s'"
            "; serializedEvents [length: %"PRIu64"]:\n%.*s"
            , (UInt64) userAgentHttpHeader.length, (int) userAgentHttpHeader.length, userAgentHttpHeader.begin
            , (UInt64) serializedEvents.length, (int) serializedEvents.length, serializedEvents.begin );
    textOutputStreamRewind( &txtOutStream );

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
                                      , userAgentHttpHeader
                                      , serializedEvents ) );

    backgroundBackendComm->dataToSendTotalSize += serializedEvents.length;
    ++backgroundBackendComm->nextEventsBatchId;

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
            "; serializedEvents [length: %"PRIu64"]:\n%.*s"
            , (UInt64) serializedEvents.length, (int) serializedEvents.length, serializedEvents.begin );

    return resultCode;

    failure:
    goto finally;
}

ResultCode sendEventsToApmServer( const ConfigSnapshot* config, StringView userAgentHttpHeader, StringView serializedEvents )
{
    ResultCode resultCode;
    char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
    TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );

    ELASTIC_APM_LOG_DEBUG(
            "Handling request to send events..."
            "; config: { serverUrl: %s, disableSend: %s, serverTimeout: %s }"
            "; userAgentHttpHeader [length: %"PRIu64"]: `%.*s'"
            "; serializedEvents [length: %"PRIu64"]:\n%.*s"
            , config->serverUrl
            , boolToString( config->disableSend )
            , streamDuration( config->serverTimeout, &txtOutStream )
            , (UInt64) userAgentHttpHeader.length, (int) userAgentHttpHeader.length, userAgentHttpHeader.begin
            , (UInt64) serializedEvents.length, (int) serializedEvents.length, serializedEvents.begin );
    textOutputStreamRewind( &txtOutStream );

    String dbgAsyncBackendCommReason = NULL;
    bool shouldSendAsync = deriveAsyncBackendComm( config, &dbgAsyncBackendCommReason );
    ELASTIC_APM_LOG_DEBUG( "async_backend_comm (asyncBackendComm) configuration option is %s - sending events %s"
                           , dbgAsyncBackendCommReason, ( shouldSendAsync ? "asynchronously" : "synchronously" ) );
    if ( shouldSendAsync )
    {
        ELASTIC_APM_CALL_IF_FAILED_GOTO( backgroundBackendCommEnsureInited( config ) );
        ELASTIC_APM_CALL_IF_FAILED_GOTO( enqueueEventsToSendToApmServer( userAgentHttpHeader, serializedEvents ) );
    }
    else
    {
        ELASTIC_APM_CALL_IF_FAILED_GOTO( syncSendEventsToApmServer( config, userAgentHttpHeader, serializedEvents ) );
    }

    resultCode = resultSuccess;
    finally:
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    return resultCode;

    failure:
    goto finally;
}

ResultCode resetBackgroundBackendCommStateInForkedChild()
{
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "g_backgroundBackendComm %s NULL", (g_backgroundBackendComm == NULL) ? "==" : "!=" );

    ResultCode resultCode;

    if ( g_backgroundBackendComm != NULL )
    {
        ELASTIC_APM_CALL_IF_FAILED_GOTO( unwindBackgroundBackendComm( &g_backgroundBackendComm, /* timeoutAbsUtc: */ NULL, /* isCreatedByThisProcess */ false ) );
    }

    resultCode = resultSuccess;
    finally:
    ELASTIC_APM_LOG_DEBUG_RESULT_CODE_FUNCTION_EXIT();
    return resultCode;

    failure:
    goto finally;
}
