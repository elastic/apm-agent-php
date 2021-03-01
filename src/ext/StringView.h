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

#pragma once

#include <string.h>
#include "basic_types.h"
#include "elastic_apm_assert.h"

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

#define ELASTIC_APM_ASSERT_VALID_STRING_VIEW( strView ) \
    ELASTIC_APM_ASSERT( isValidStringView( (strView) ) \
                        , "begin: %p, length: %"PRIu64, (strView).begin, (UInt64)((strView).length) )

static inline
StringView makeStringView( const char* begin, size_t length )
{
    ELASTIC_APM_ASSERT( ( length == 0 ) || isValidPtr( begin )
            , "begin: %p, length: %"PRIu64, begin, (UInt64)length );

    StringView strView = { .begin = begin, .length = length };

    ELASTIC_APM_ASSERT_VALID_STRING_VIEW( strView );
    return strView;
}

static inline
StringView makeStringViewFromBeginEnd( const char* begin, const char* end )
{
    ELASTIC_APM_ASSERT( end == begin || ( isValidPtr( begin ) && isValidPtr( end ) && begin <= end )
                       , "begin: %p, end: %p", begin, end );

    StringView strView = { .begin = begin, .length = end - begin };

    ELASTIC_APM_ASSERT_VALID_STRING_VIEW( strView );
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
    ELASTIC_APM_ASSERT_VALID_STRING_VIEW( strView );

    return strView.length == 0;
}

static inline
const char* stringViewEnd( StringView strView )
{
    ELASTIC_APM_ASSERT_VALID_STRING_VIEW( strView );

    return strView.begin + strView.length;
}

static inline
StringView makeStringViewFromLiteralHelper( const char* begin, size_t size )
{
    ELASTIC_APM_ASSERT_VALID_PTR( begin );
    ELASTIC_APM_ASSERT_GE_UINT64( size, 1 );
    ELASTIC_APM_ASSERT_EQ_CHAR( begin[ size - 1 ], '\0' );

    return makeStringView( begin, /* length: */ size - 1 );
}

#define ELASTIC_APM_STRING_LITERAL_TO_VIEW( stringLiteral ) ( makeStringViewFromLiteralHelper( (stringLiteral), sizeof( (stringLiteral) ) ) )

static inline
StringView makeStringViewFromString( String zeroTermStr )
{
    ELASTIC_APM_ASSERT_VALID_PTR( zeroTermStr );

    return makeStringView( zeroTermStr, /* length: */ strlen( zeroTermStr ) );
}
