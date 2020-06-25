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

#define ELASTIC_APM_NUMBER_OF_MICROSECONDS_IN_SECOND (1000000)
#define ELASTIC_APM_NUMBER_OF_MICROSECONDS_IN_MILLISECOND (1000)
