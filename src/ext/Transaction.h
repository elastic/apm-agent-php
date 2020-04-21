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

#include "elasticapm_assert.h"
#include "elasticapm_alloc.h"
#include "ResultCode.h"
#include "TextOutputStream.h"


enum
{
    errorsBufferSize = 1024 * 1024,
    exceptionsBufferSize = 1024 * 1024
};

struct Transaction
{
    TimePoint startTime;
    char id[ executionSegmentIdAsHexStringBufferSize ];
    char traceId[ traceIdAsHexStringBufferSize ];

    char* errorsBuffer;
    TextOutputStream errorsTextOutputStream;

    char* exceptionsBuffer;
    TextOutputStream exceptionsTextOutputStream;
};

typedef struct Transaction Transaction;

static inline void deleteTransactionAndSetToNull( Transaction** pTransaction )
{
    ELASTICAPM_ASSERT_VALID_PTR( pTransaction );

    Transaction* const transaction = *pTransaction;
    if ( transaction == NULL ) return;
    ELASTICAPM_ASSERT_VALID_PTR( transaction );

    ELASTICAPM_EFREE_STRING_AND_SET_TO_NULL( errorsBufferSize, transaction->errorsBuffer );
    ELASTICAPM_EFREE_STRING_AND_SET_TO_NULL( exceptionsBufferSize, transaction->exceptionsBuffer );
    // TODO: Sergey Kleyman: Try commenting out to test memory tracker
    ELASTICAPM_EFREE_INSTANCE_AND_SET_TO_NULL( Transaction, *pTransaction );
}

static inline ResultCode newTransaction( Transaction** pNewTransaction )
{
    ELASTICAPM_ASSERT_VALID_OUT_PTR_TO_PTR( pNewTransaction );

    ResultCode resultCode;
    Transaction* newTransaction = NULL;

    ELASTICAPM_EMALLOC_INSTANCE_IF_FAILED_GOTO( Transaction, newTransaction );
    ELASTICAPM_ZERO_STRUCT( newTransaction );

    getCurrentTime( &newTransaction->startTime );
    ELASTICAPM_GEN_RANDOM_ID_AS_HEX_STRING( executionSegmentIdSizeInBytes, newTransaction->id );
    ELASTICAPM_GEN_RANDOM_ID_AS_HEX_STRING( traceIdSizeInBytes, newTransaction->traceId );

    newTransaction->errorsBuffer = NULL;
    newTransaction->exceptionsBuffer = NULL;

    resultCode = resultSuccess;
    *pNewTransaction = newTransaction;

    finally:
    return resultCode;

    failure:
    deleteTransactionAndSetToNull( &newTransaction );
    goto finally;
}
