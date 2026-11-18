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

#include <cstddef>
#include <string>
#include <string_view>
#include <vector>

/**
 * One queued batch of events as produced by the PHP part: a valid ND-JSON stream that starts with the metadata line
 * (`{"metadata":...}`) followed by zero or more event lines separated by '\n'.
 */
struct EventsBatchView
{
    std::string_view userAgentHttpHeader;
    std::string_view serializedEvents;
};

/**
 * Max size of the body of one request to APM Server's intake API when multiple queued batches are combined.
 * The same as the default value of api_request_size in other Elastic APM agents.
 * (APM Server limits the size of a single event, not of the whole request.)
 */
constexpr size_t maxCombinedEventsBodySize = 768 * 1024;

/**
 * @return the metadata line of the batch, i.e. everything before the first '\n' (the whole batch if there is none)
 */
std::string_view getEventsBatchMetadataLine( std::string_view serializedEvents );

/**
 * @return everything after the metadata line (without the '\n' separating it), empty if there are no events
 */
std::string_view getEventsBatchEventLines( std::string_view serializedEvents );

/**
 * Combines consecutive batches (starting with the first one) into one ND-JSON body that is sent as a single request:
 * the first batch as is and, for each following batch, only its event lines (the metadata line is sent once).
 *
 * Combining stops at the first batch that
 *      - has a different user agent or a different metadata line than the first batch
 *        (so the metadata that applies to every event is exactly the same as if batches were sent separately), or
 *      - would make the body bigger than maxBodySize.
 * The first batch is always taken, even if it is bigger than maxBodySize.
 *
 * @param batches if empty then nothing is combined and 0 is returned
 * @param combinedBody out
 * @return number of batches (counting from the first one) that were combined into combinedBody
 */
size_t combineEventsBatches( const std::vector< EventsBatchView >& batches, size_t maxBodySize, std::string& combinedBody );
