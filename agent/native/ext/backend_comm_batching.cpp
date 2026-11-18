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

#include "backend_comm_batching.h"

std::string_view getEventsBatchMetadataLine( std::string_view serializedEvents )
{
    return serializedEvents.substr( 0, serializedEvents.find( '\n' ) );
}

std::string_view getEventsBatchEventLines( std::string_view serializedEvents )
{
    size_t metadataLineEnd = serializedEvents.find( '\n' );
    return metadataLineEnd == std::string_view::npos ? std::string_view() : serializedEvents.substr( metadataLineEnd + 1 );
}

size_t combineEventsBatches( const std::vector< EventsBatchView >& batches, size_t maxBodySize, std::string& combinedBody )
{
    combinedBody.clear();
    if ( batches.empty() )
    {
        return 0;
    }

    const EventsBatchView& first = batches.front();
    const std::string_view firstMetadataLine = getEventsBatchMetadataLine( first.serializedEvents );

    combinedBody.append( first.serializedEvents );
    size_t count = 1;

    for ( ; count < batches.size(); ++count )
    {
        const EventsBatchView& batch = batches[ count ];
        if ( batch.userAgentHttpHeader != first.userAgentHttpHeader
             || getEventsBatchMetadataLine( batch.serializedEvents ) != firstMetadataLine )
        {
            break;
        }

        std::string_view eventLines = getEventsBatchEventLines( batch.serializedEvents );
        // A trailing '\n' of a batch would produce an empty line in the middle of the combined body
        while ( ! eventLines.empty() && eventLines.back() == '\n' )
        {
            eventLines.remove_suffix( 1 );
        }
        if ( ! eventLines.empty() )
        {
            // +1 for '\n' that separates the last line of the previous batch from the first event line of this one
            if ( combinedBody.size() + 1 + eventLines.size() > maxBodySize )
            {
                break;
            }
            if ( combinedBody.back() != '\n' )
            {
                combinedBody.push_back( '\n' );
            }
            combinedBody.append( eventLines );
        }
    }

    return count;
}
