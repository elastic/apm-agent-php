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

#pragma once

#include <stdbool.h>
#include <php.h>
#include <Zend/zend.h>

#ifdef PHP_WIN32
#   include <win32/time.h>
#else
#   include <sys/time.h>
#endif

#include "constants.h"
#include "basic_types.h"
#include "ResultCode.h"
#include "elasticapm_assert.h"


#define FOR_EACH_INDEX_START_END( indexVarType, indexVar, rangeStart, rangeExcludedEnd ) \
    for ( indexVarType indexVar = rangeStart ; (indexVar) < (rangeExcludedEnd) ; ++(indexVar) )

#define FOR_EACH_INDEX( indexVarType, indexVar, rangeSize ) \
    FOR_EACH_INDEX_START_END( indexVarType, indexVar, 0, rangeSize )

static inline bool isEmtpyStr( const char* str )
{
    return strlen( str ) == 0;
}

static inline bool isNullOrEmtpyStr( const char* str )
{
    return str == NULL || isEmtpyStr( str );
}

static inline void replaceChar( MutableString str, char originalChar, char replacementChar )
{
    ASSERT_VALID_PTR( str );

    for ( size_t i = 0 ; str[ i ] != '\0' ; ++i ) if ( str[ i ] == originalChar ) str[ i ] = replacementChar;
}

static inline bool isEmtpyZstr( const zend_string* zStr )
{
    return ZSTR_LEN( zStr ) == 0;
}

static inline bool isNullOrEmtpyZstr( const zend_string* zStr )
{
    return zStr == NULL || isEmtpyZstr( zStr );
}

static inline const char* boolToStr( bool boolValue )
{
    return boolValue ? "true" : "false";
}

struct TimePoint
{
    struct timeval systemClockTime;
};

typedef struct TimePoint TimePoint;

static inline void getCurrentTime( TimePoint* result )
{
    gettimeofday( &( result->systemClockTime ), /* timezone_info: */ NULL );
}

static inline UInt64 timePointToEpochMicroseconds( const TimePoint* timePoint )
{
    ASSERT_VALID_PTR( timePoint );

    return timePoint->systemClockTime.tv_sec * (UInt64) ( NUMBER_OF_MICROSECONDS_IN_SECOND ) + timePoint->systemClockTime.tv_usec;
}

static inline UInt64 getCurrentTimeEpochMicroseconds()
{
    TimePoint currentTime;
    getCurrentTime( &currentTime );
    return timePointToEpochMicroseconds( &currentTime );
}

static inline Int64 durationMicroseconds( const TimePoint* start, const TimePoint* end )
{
    ASSERT_VALID_PTR( start );
    ASSERT_VALID_PTR( end );

    return timePointToEpochMicroseconds( end ) - timePointToEpochMicroseconds( start );
}

// in ms with 3 decimal points
static inline double durationMicrosecondsToMilliseconds( Int64 durationMicros )
{
    return ( (double) durationMicros ) / NUMBER_OF_MICROSECONDS_IN_MILLISECOND;
}

ResultCode genRandomIdHexString( UInt8 idSizeBytes, String* pResult );

ResultCode elasticApmExecutePhpFile( const char *filename TSRMLS_DC );

#define EFREE_AND_SET_TO_NULL( ptr ) \
    do { \
        if ( (ptr) != NULL ) \
        { \
            efree( (void*)(ptr) ); \
            (ptr) = NULL; \
        } \
    } while ( false ) \
    /**/

#ifdef PHP_WIN32
extern void* g_unusedParameterHelper;
#define UNUSED_PARAMETER( parameter ) do { g_unusedParameterHelper = (void*)(&(parameter)); } while ( false )
#define UNUSED_LOCAL_VAR( localVar ) do { g_unusedParameterHelper = (void*)(&(localVar)); } while ( false )
#else
#define UNUSED_PARAMETER( paramter )
#define UNUSED_LOCAL_VAR( localVar )
#endif

#define ELASTIC_PP_STRINGIZE_IMPL( token ) #token
#define ELASTIC_PP_STRINGIZE( token ) ELASTIC_PP_STRINGIZE_IMPL( token )

struct StringView
{
    const char* begin;
    size_t length;
};

typedef struct StringView StringView;

static inline StringView makeStringView( const char* begin, size_t length )
{
    StringView result = { .begin = begin, .length = length };
    return result;
}

#define STRING_LITERAL_TO_VIEW( stringLiteral ) makeStringView( (stringLiteral), sizeof( stringLiteral ) - 1 )

static inline bool isZarray( const zval* zValue )
{
    ASSERT_VALID_PTR( zValue );

    return Z_TYPE_P( zValue ) == IS_ARRAY;
}

static inline const zval* findInZarrayByStrKey( const zend_array* zArray, StringView key )
{
    return zend_hash_str_find( zArray, key.begin, key.length );
}
