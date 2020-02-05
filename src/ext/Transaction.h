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

#ifndef ELASTICAPM_TRANSACTION_H
#define ELASTICAPM_TRANSACTION_H

#include "utils.h"
#include "elasticapm_assert.h"
#include "ResultCode.h"

struct Transaction
{
    TimePoint startTime;
    String id;
    String traceId;
};

typedef struct Transaction Transaction;

static inline void deleteTransactionAndSetToNull( Transaction** ppThisObj )
{
    ASSERT_VALID_PTR( ppThisObj );
    Transaction* const thisObj = *ppThisObj;
    if ( thisObj == NULL ) return;

    ASSERT_VALID_IN_PTR_TO_PTR( ppThisObj );

    EFREE_AND_SET_TO_NULL( thisObj->id );
    EFREE_AND_SET_TO_NULL( thisObj->traceId );
    EFREE_AND_SET_TO_NULL( *ppThisObj );
}

static inline ResultCode newTransaction( Transaction** ppThisObj )
{
    ResultCode resultCode;
    Transaction* thisObj = NULL;

    ASSERT_VALID_OUT_PTR_TO_PTR( ppThisObj );

    ALLOC_IF_FAILED_GOTO( Transaction, thisObj );
    getCurrentTime( &thisObj->startTime );
    CALL_IF_FAILED_GOTO( genRandomIdHexString( EXECUTION_SEGMENT_ID_SIZE_IN_BYTES, &thisObj->id ) );
    CALL_IF_FAILED_GOTO( genRandomIdHexString( TRACE_ID_SIZE_IN_BYTES, &thisObj->traceId ) );

    resultCode = resultSuccess;
    *ppThisObj = thisObj;

    finally:
    return resultCode;

    failure:
    deleteTransactionAndSetToNull( &thisObj );
    goto finally;
}

#endif /* #ifndef ELASTICAPM_TRANSACTION_H */
