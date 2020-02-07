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

#define NUMBER_OF_MICROSECONDS_IN_SECOND (1000000)
#define NUMBER_OF_MICROSECONDS_IN_MILLISECOND (1000)

// https://github.com/elastic/apm-server/blob/6.5/docs/spec/transactions/v2_transaction.json#L12
// https://github.com/elastic/apm-server/blob/6.5/docs/spec/spans/v2_span.json#L11
// 64 random bits ID
#define EXECUTION_SEGMENT_ID_SIZE_IN_BYTES (8)

// https://github.com/elastic/apm-server/blob/6.5/docs/spec/transactions/v2_transaction.json#L16
// 128 random bits ID
#define TRACE_ID_SIZE_IN_BYTES (16)

// https://github.com/elastic/apm-server/blob/6.5/docs/spec/errors/v2_error.json#L13
// 128 random bits ID
#define ERROR_ID_SIZE_IN_BYTES (16)
