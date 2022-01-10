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

#include "basic_macros.h"
#include "elastic_apm_assert.h"

// +1 for terminating '\0'
#define ELASTIC_APM_CALC_ID_AS_HEX_STRING_BUFFER_SIZE( idSizeBytes ) ( (idSizeBytes) * 2 +1 )

enum
{
    // https://github.com/elastic/apm-server/blob/6.5/docs/spec/transactions/v2_transaction.json#L12
    // https://github.com/elastic/apm-server/blob/6.5/docs/spec/spans/v2_span.json#L11
    // 64 random bits ID
    executionSegmentIdSizeInBytes = 8,
    executionSegmentIdAsHexStringBufferSize = ELASTIC_APM_CALC_ID_AS_HEX_STRING_BUFFER_SIZE( executionSegmentIdSizeInBytes ),

    // https://github.com/elastic/apm-server/blob/6.5/docs/spec/transactions/v2_transaction.json#L16
    // 128 random bits ID
    traceIdSizeInBytes = 16,
    traceIdAsHexStringBufferSize = ELASTIC_APM_CALC_ID_AS_HEX_STRING_BUFFER_SIZE( traceIdSizeInBytes ),

    // https://github.com/elastic/apm-server/blob/6.5/docs/spec/errors/v2_error.json#L13
    // 128 random bits ID
    errorIdSizeInBytes = 16,
    errorIdAsHexStringBufferSize = ELASTIC_APM_CALC_ID_AS_HEX_STRING_BUFFER_SIZE( errorIdSizeInBytes ),

    idMaxSizeInBytes = 100
};
ELASTIC_APM_STATIC_ASSERT( executionSegmentIdSizeInBytes <= idMaxSizeInBytes );
ELASTIC_APM_STATIC_ASSERT( traceIdSizeInBytes <= idMaxSizeInBytes );
ELASTIC_APM_STATIC_ASSERT( errorIdSizeInBytes <= idMaxSizeInBytes );

#define ELASTIC_APM_NUMBER_OF_MICROSECONDS_IN_SECOND (1000000) // 10^6
#define ELASTIC_APM_NUMBER_OF_MICROSECONDS_IN_MILLISECOND (1000) // 10^3
#define ELASTIC_APM_NUMBER_OF_NANOSECONDS_IN_SECOND (1000000000L) // 10^9
#define ELASTIC_APM_NUMBER_OF_NANOSECONDS_IN_MILLISECOND (1000000L) // 10^6
