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

enum ResultCode
{
    resultSuccess,
    resultOutOfMemory,
    resultFailure
};
typedef enum ResultCode ResultCode;

#define ALLOC_IF_FAILED_GOTO( type, outPtr ) \
    do { \
        void* eMallocIfFiledGotoTmpPtr = emalloc( sizeof( type ) ); \
        if ( eMallocIfFiledGotoTmpPtr == NULL ) \
        { \
            resultCode = resultOutOfMemory; \
            goto failure; \
        } \
        (outPtr) = (type*)(eMallocIfFiledGotoTmpPtr); \
    } while( false )

#define ALLOC_ARRAY_IF_FAILED_GOTO( type, arrayNumberOfElements, outPtr ) \
    do { \
        void* eMallocIfFailedGotoTmpPtr = emalloc( sizeof( type ) * (arrayNumberOfElements) ); \
        if ( eMallocIfFailedGotoTmpPtr == NULL ) \
        { \
            resultCode = resultOutOfMemory; \
            goto failure; \
        } \
        (outPtr) = (type*)(eMallocIfFailedGotoTmpPtr); \
    } while( false )

#define ALLOC_STRING_IF_FAILED_GOTO( stringLength, outPtr ) ALLOC_ARRAY_IF_FAILED_GOTO( char, stringLength + 1, outPtr )

#define CALL_IF_FAILED_GOTO( expr ) \
    do { \
        resultCode = (expr); \
        if ( resultCode != resultSuccess ) goto failure; \
    } while( false )
