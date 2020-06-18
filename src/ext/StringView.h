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

#include <string.h>
#include "basic_types.h"
#include "elasticapm_assert.h"

struct StringView
{
    const char* begin;
    size_t length;
};
typedef struct StringView StringView;

static inline
bool isValidStringView( StringView strView )
{
    return ( strView.length == 0 ) || isValidPtr( strView.begin );
}

#define ELASTICAPM_ASSERT_VALID_STRING_VIEW( strView ) \
    ELASTICAPM_ASSERT( isValidStringView( (strView) ) \
                        , "begin: %p, length: %"PRIu64, (strView).begin, (UInt64)((strView).length) )

static inline
StringView makeStringView( const char* begin, size_t length )
{
    ELASTICAPM_ASSERT( ( length == 0 ) || isValidPtr( begin )
            , "begin: %p, length: %"PRIu64, begin, (UInt64)length );

    StringView strView = { .begin = begin, .length = length };

    ELASTICAPM_ASSERT_VALID_STRING_VIEW( strView );
    return strView;
}

static inline
StringView makeStringViewFromBeginEnd( const char* begin, const char* end )
{
    ELASTICAPM_ASSERT( end == begin || ( isValidPtr( begin ) && isValidPtr( end ) && begin <= end )
                       , "begin: %p, end: %p", begin, end );

    StringView strView = { .begin = begin, .length = end - begin };

    ELASTICAPM_ASSERT_VALID_STRING_VIEW( strView );
    return strView;
}

static inline
StringView makeEmptyStringView()
{
    return makeStringView( NULL, 0 );
}

static inline
bool isEmptyStringView( StringView strView )
{
    ELASTICAPM_ASSERT_VALID_STRING_VIEW( strView );

    return strView.length == 0;
}

static inline
const char* stringViewEnd( StringView strView )
{
    ELASTICAPM_ASSERT_VALID_STRING_VIEW( strView );

    return strView.begin + strView.length;
}

static inline
StringView makeStringViewFromLiteralHelper( const char* begin, size_t size )
{
    ELASTICAPM_ASSERT_VALID_PTR( begin );
    ELASTICAPM_ASSERT_GE_UINT64( size, 1 );
    ELASTICAPM_ASSERT_EQ_CHAR( begin[ size - 1 ], '\0' );

    return makeStringView( begin, /* length: */ size - 1 );
}

#define ELASTICAPM_STRING_LITERAL_TO_VIEW( stringLiteral ) ( makeStringViewFromLiteralHelper( (stringLiteral), sizeof( (stringLiteral) ) ) )

static inline
StringView makeStringViewFromString( String zeroTermStr )
{
    ELASTICAPM_ASSERT_VALID_PTR( zeroTermStr );

    return makeStringView( zeroTermStr, /* length: */ strlen( zeroTermStr ) );
}
