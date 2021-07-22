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
#if defined(PHP_WIN32) && ! defined(CURL_STATICLIB)
#   define CURL_STATICLIB
#endif
#include <stdio.h>
#include <curl/curl.h>
#include "elastic_apm_alloc.h"
#include "elastic_apm_version.h"

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


#ifdef ELASTIC_APM_PLATFORM_HAS_GETLINE
#   define ELASTIC_APM_SHOULD_LOG_LIBCURL_VERBOSE
#endif // ELASTIC_APM_PLATFORM_HAS_GETLINE

#ifdef ELASTIC_APM_SHOULD_LOG_LIBCURL_VERBOSE
static void logLibCurlVerboseOutput( LogLevel logLevel, FILE* verbOutFile )
{
    int fflushRetVal = 0;
    char* lineBuf = NULL;
    size_t lineBufSize = 0;

    fflushRetVal = fflush( verbOutFile );
    if ( fflushRetVal != 0 )
    {
        int last_errno = errno;
        char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
        TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
        ELASTIC_APM_LOG_ERROR( "Failed to flush file with libcurl verbose information - fflush() returned non-zero value."
                               " fflushRetVal: %d. errno: %d (%s)", fflushRetVal, last_errno, streamErrNo( last_errno, &txtOutStream ) );
        goto failure;
    }

    rewind( verbOutFile );

    ELASTIC_APM_LOG_WITH_LEVEL( logLevel, "libcurl's verbose output BEGIN" );
    while ( getline( /* in,out */ &lineBuf, /* in,out */ &lineBufSize, verbOutFile ) != -1 )
    {
        ELASTIC_APM_LOG_WITH_LEVEL( logLevel, "libcurl's verbose output: %s", lineBuf );
    }
    ELASTIC_APM_LOG_WITH_LEVEL( logLevel, "libcurl's verbose output END" );

    finally:

    if ( lineBuf != NULL ) free( lineBuf );

    return;

    failure:
    goto finally;
}
#endif // ELASTIC_APM_SHOULD_LOG_LIBCURL_VERBOSE

ResultCode sendEventsToApmServer( double serverTimeoutMilliseconds, const ConfigSnapshot* config, StringView serializedEvents )
{
    long serverTimeoutMillisecondsLong = (long) ceil( serverTimeoutMilliseconds );
    ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG(
            "Sending events to APM Server... serverTimeoutMilliseconds: %f (as integer: %"PRIu64")"
            " serializedEvents [length: %"PRIu64"]:\n%.*s"
            , serverTimeoutMilliseconds, (UInt64) serverTimeoutMillisecondsLong
            , (UInt64) serializedEvents.length, (int) serializedEvents.length, serializedEvents.begin );

    ResultCode resultCode;
    CURL* curl = NULL;
    CURLcode result;

    #ifdef ELASTIC_APM_SHOULD_LOG_LIBCURL_VERBOSE
    FILE* verbOutFile = NULL;
    #endif // ELASTIC_APM_SHOULD_LOG_LIBCURL_VERBOSE

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

    #ifdef ELASTIC_APM_SHOULD_LOG_LIBCURL_VERBOSE
    verbOutFile = tmpfile();
    if ( verbOutFile == NULL )
    {
        int last_errno = errno;
        char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
        TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
        ELASTIC_APM_LOG_ERROR( "Failed to open temporary file for libcurl's verbose information - tmpfile() returned NULL."
                               " errno: %d (%s)", last_errno, streamErrNo( last_errno, &txtOutStream ) );
        resultCode = resultFailure;
        goto failure;
    }
    ELASTIC_APM_CURL_EASY_SETOPT( curl, CURLOPT_STDERR, verbOutFile );
    ELASTIC_APM_CURL_EASY_SETOPT( curl, CURLOPT_VERBOSE, 1L );
    #endif // ELASTIC_APM_SHOULD_LOG_LIBCURL_VERBOSE

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
        ELASTIC_APM_LOG_ERROR( "Sending events to APM Server failed."
                               " URL: `%s'."
                               " Error message: `%s'."
                               " verify_server_cert: %s."
                               " Is API key set: %s."
                               " Is secret token set: %s."
                               " serverTimeoutMilliseconds: %f (as integer: %"PRIu64")."
                               " serializedEvents.length: %"PRIu64"."
                               , url
                               , curl_easy_strerror( result )
                               , streamBool( config->verifyServerCert, &txtOutStream )
                               , streamBool( isNullOrEmtpyString( config->apiKey ), &txtOutStream )
                               , streamBool( isNullOrEmtpyString( config->secretToken ), &txtOutStream )
                               , serverTimeoutMilliseconds, (UInt64) serverTimeoutMillisecondsLong
                               , (UInt64) serializedEvents.length );

        #ifdef ELASTIC_APM_SHOULD_LOG_LIBCURL_VERBOSE
        logLibCurlVerboseOutput( logLevel_error, verbOutFile );
        #endif // ELASTIC_APM_SHOULD_LOG_LIBCURL_VERBOSE

        resultCode = resultFailure;
        goto failure;
    }

    long responseCode;
    curl_easy_getinfo( curl, CURLINFO_RESPONSE_CODE, &responseCode );
    ELASTIC_APM_LOG_DEBUG( "Sent events to APM Server. Response HTTP code: %ld. URL: `%s'.", responseCode, url );

    resultCode = resultSuccess;

    finally:

    #ifdef ELASTIC_APM_SHOULD_LOG_LIBCURL_VERBOSE
    if ( verbOutFile != NULL )
    {
        fclose( verbOutFile );
        verbOutFile = NULL;
    }
    #endif // ELASTIC_APM_SHOULD_LOG_LIBCURL_VERBOSE

    if ( curl != NULL )
    {
        curl_easy_cleanup( curl );
        curl = NULL;
    }

    ELASTIC_APM_LOG_DEBUG_FUNCTION_EXIT_MSG( "resultCode: %s (%d)", resultCodeToString( resultCode ), resultCode );
    return resultCode;

    failure:
    goto finally;
}

#undef ELASTIC_APM_CURL_EASY_SETOPT
