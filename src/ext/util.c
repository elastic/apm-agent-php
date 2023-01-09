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

#include "util.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_UTIL

StringView trimStringView( StringView src )
{
    size_t beginIndex = 0;
    size_t endIndex = src.length;
    for ( ; beginIndex < src.length && ( isWhiteSpace( src.begin[ beginIndex ] ) ) ; ++beginIndex );
    for ( ; endIndex > beginIndex && ( isWhiteSpace( src.begin[ endIndex - 1 ] ) ) ; --endIndex );

    return makeStringView( src.begin + beginIndex, endIndex - beginIndex );
}

StringView findEndOfLineSequence( StringView text )
{
    // The order in endOfLineSequences is important because we need to check longer sequences first
    StringView endOfLineSequences[] =
            {
                    ELASTIC_APM_STRING_LITERAL_TO_VIEW( "\r\n" )
                    , ELASTIC_APM_STRING_LITERAL_TO_VIEW( "\n" )
                    , ELASTIC_APM_STRING_LITERAL_TO_VIEW( "\r" )
            };

    ELASTIC_APM_FOR_EACH_INDEX( textPos, text.length )
    {
        ELASTIC_APM_FOR_EACH_INDEX( eolSeqIndex, ELASTIC_APM_STATIC_ARRAY_SIZE( endOfLineSequences ) )
        {
            if ( text.length - textPos < endOfLineSequences[ eolSeqIndex ].length ) continue;

            StringView eolSeqCandidate = makeStringView( &( text.begin[ textPos ] ), endOfLineSequences[ eolSeqIndex ].length );
            if ( areStringViewsEqual( eolSeqCandidate, endOfLineSequences[ eolSeqIndex ] ) )
            {
                return eolSeqCandidate;
            }
        }
    }

    return makeEmptyStringView();
}
