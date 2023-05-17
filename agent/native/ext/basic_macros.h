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

#define ELASTIC_APM_UNUSED( var ) (void)(var)

#define ELASTIC_APM_PP_STRINGIZE_IMPL( token ) #token
#define ELASTIC_APM_PP_STRINGIZE( token ) ELASTIC_APM_PP_STRINGIZE_IMPL( token )

#define ELASTIC_APM_PP_CONCAT_IMPL( token1, token2 ) token1##token2
#define ELASTIC_APM_PP_CONCAT( token1, token2 ) ELASTIC_APM_PP_CONCAT_IMPL( token1, token2 )

#define ELASTIC_APM_IS_IN_INCLUSIVE_RANGE( rangeBegin, x, rangeEnd ) ( ( (rangeBegin) <= (x) ) && ( (x) <= (rangeEnd) ) )

#define ELASTIC_APM_IS_IN_END_EXCLUDED_RANGE( rangeBeginIncluded, x, rangeEndExcluded ) ( ( (rangeBeginIncluded) <= (x) ) && ( (x) < (rangeEndExcluded) ) )

#define ELASTIC_APM_STATIC_ARRAY_SIZE( array ) ( ( sizeof( (array) ) ) / sizeof( (array)[ 0 ] ) )

#define ELASTIC_APM_FOR_EACH_INDEX_START_END( indexVarType, indexVar, rangeStart, rangeExcludedEnd ) \
    for ( indexVarType indexVar = rangeStart ; (indexVar) < (rangeExcludedEnd) ; ++indexVar )

#define ELASTIC_APM_FOR_EACH_INDEX_EX( indexVarType, indexVar, rangeSize ) \
    ELASTIC_APM_FOR_EACH_INDEX_START_END( indexVarType, indexVar, 0, rangeSize )

#define ELASTIC_APM_FOR_EACH_INDEX( indexVar, rangeSize ) ELASTIC_APM_FOR_EACH_INDEX_EX( size_t, indexVar, rangeSize )

#define ELASTIC_APM_REPEAT_N_TIMES( numberOfTimes ) \
    ELASTIC_APM_FOR_EACH_INDEX_EX( size_t, ELASTIC_APM_PP_CONCAT( elastic_apm_repeat_n_times_index_var, __LINE__ ), numberOfTimes )
/**/

#define ELASTIC_APM_FOR_EACH_BACKWARDS( indexVar, rangeSize ) \
    for \
    ( \
        /* init */ \
        size_t \
            elastic_apm_for_each_backwards_number_of_elements_remaining = (rangeSize), \
            (indexVar) = (rangeSize) == 0 ? 0 : (rangeSize) - 1; \
        \
        /* stop condition */ \
        elastic_apm_for_each_backwards_number_of_elements_remaining != 0 ; \
        \
        /* step */ \
            --elastic_apm_for_each_backwards_number_of_elements_remaining, \
            (indexVar) = ( (indexVar) == 0 ) ? 0 : (indexVar) - 1 \
    )

#define ELASTIC_APM_MIN( a, b ) ( ( (a) < (b) ) ? (a) : (b) )
#define ELASTIC_APM_MAX( a, b ) ( ( (a) > (b) ) ? (a) : (b) )

#define ELASTIC_APM_ZERO_STRUCT( structPtr ) memset( (structPtr), 0, sizeof( *(structPtr) ) )


#if ( ! defined( PHP_WIN32 ) ) || defined( ELASTIC_APM_UNDER_IDE )
#   define ELASTIC_APM_PRINTF_ATTRIBUTE( fmtPos, fmtArgsPos ) __attribute__ ( ( format ( printf, fmtPos, fmtArgsPos ) ) )
#else
#   define ELASTIC_APM_PRINTF_ATTRIBUTE( fmtPos, fmtArgsPos )
#endif

#if ( ! defined( PHP_WIN32 ) ) || defined( ELASTIC_APM_UNDER_IDE )
#   define ELASTIC_APM_NO_RETURN_ATTRIBUTE __attribute__ ( ( noreturn ) )
#else
#   define ELASTIC_APM_NO_RETURN_ATTRIBUTE
#endif


#define ELASTIC_APM_PP_EXPAND( somePPTokenToExpand ) somePPTokenToExpand

//////////////////////////////////////////////////////////////////////////////
//
// ELASTIC_APM_PP_VARIADIC_ARGS_COUNT
//

#ifdef _MSC_VER // Microsoft compilers

#   define ELASTIC_APM_PP_VARIADIC_ARGS_COUNT_HELPER_AUGMENTER( ... ) unusedPPToken, __VA_ARGS__
#   define ELASTIC_APM_PP_VARIADIC_ARGS_COUNT_HELPER( dummyArg_1, dummyArg_2, dummyArg_3, dummyArg_4, dummyArg_5, dummyArg_6, dummyArg_7, dummyArg_8, dummyArg_9, dummyArg_10, dummyArg_11, count, ... ) count
#   define ELASTIC_APM_PP_VARIADIC_ARGS_COUNT_HELPER_EXPAND_ARGS( ... ) ELASTIC_APM_PP_EXPAND( ELASTIC_APM_PP_VARIADIC_ARGS_COUNT_HELPER( __VA_ARGS__, 10, 9, 8, 7, 6, 5, 4, 3, 2, 1, 0 ) )
#   define ELASTIC_APM_PP_VARIADIC_ARGS_COUNT( ... ) ELASTIC_APM_PP_VARIADIC_ARGS_COUNT_HELPER_EXPAND_ARGS( ELASTIC_APM_PP_VARIADIC_ARGS_COUNT_HELPER_AUGMENTER( __VA_ARGS__ ) )

#else // Non-Microsoft compilers

#   define ELASTIC_APM_PP_VARIADIC_ARGS_COUNT_HELPER( dummyArg_0, dummyArg_1, dummyArg_2, dummyArg_3, dummyArg_4, dummyArg_5, dummyArg_6, dummyArg_7, dummyArg_8, dummyArg_9, dummyArg_10, dummyArg_11, count, ... ) count
#   define ELASTIC_APM_PP_VARIADIC_ARGS_COUNT( ... ) ELASTIC_APM_PP_VARIADIC_ARGS_COUNT_HELPER( 0, ## __VA_ARGS__, 11, 10, 9, 8, 7, 6, 5, 4, 3, 2, 1, 0 )

#endif

//
// ELASTIC_APM_PP_VARIADIC_ARGS_COUNT
//
//////////////////////////////////////////////////////////////////////////////

#ifdef ELASTIC_APM_UNDER_IDE
#define ELASTIC_APM_SUPPRESS_UNUSED( symbol ) \
    static void* ELASTIC_APM_PP_CONCAT( g_ELASTIC_APM_SUPPRESS_UNUSED_, ELASTIC_APM_PP_CONCAT( __LINE__, ELASTIC_APM_PP_CONCAT( _, __COUNTER__ ) ) ) = &( symbol )
#else
#define ELASTIC_APM_SUPPRESS_UNUSED( symbol )
#endif

#define ELASTIC_APM_NOOP_STATEMENT ((void)(0))

#define ELASTIC_APM_FIELD_SIZEOF( StructType, field ) (sizeof( ((StructType*)NULL)->field ))

////////////////////////////////////////////////////////////////////////////////
////
//// ELASTIC_APM_IF_VA_ARGS_EMPTY_ELSE
////
#define ELASTIC_APM_IF_VA_ARGS_EMPTY_ELSE_0( ifToken, elseToken ) ifToken
#define ELASTIC_APM_IF_VA_ARGS_EMPTY_ELSE_1( ifToken, elseToken ) elseToken
#define ELASTIC_APM_IF_VA_ARGS_EMPTY_ELSE_2( ifToken, elseToken ) elseToken
#define ELASTIC_APM_IF_VA_ARGS_EMPTY_ELSE_3( ifToken, elseToken ) elseToken
#define ELASTIC_APM_IF_VA_ARGS_EMPTY_ELSE_4( ifToken, elseToken ) elseToken
#define ELASTIC_APM_IF_VA_ARGS_EMPTY_ELSE_5( ifToken, elseToken ) elseToken
#define ELASTIC_APM_IF_VA_ARGS_EMPTY_ELSE_6( ifToken, elseToken ) elseToken
#define ELASTIC_APM_IF_VA_ARGS_EMPTY_ELSE_7( ifToken, elseToken ) elseToken
#define ELASTIC_APM_IF_VA_ARGS_EMPTY_ELSE_8( ifToken, elseToken ) elseToken
#define ELASTIC_APM_IF_VA_ARGS_EMPTY_ELSE_9( ifToken, elseToken ) elseToken
#define ELASTIC_APM_IF_VA_ARGS_EMPTY_ELSE_10( ifToken, elseToken ) elseToken
#define ELASTIC_APM_IF_VA_ARGS_EMPTY_ELSE( ifToken, elseToken, ... ) \
    ELASTIC_APM_PP_EXPAND( ELASTIC_APM_PP_CONCAT( ELASTIC_APM_IF_VA_ARGS_EMPTY_ELSE_, ELASTIC_APM_PP_VARIADIC_ARGS_COUNT( __VA_ARGS__ ) ) ) ( ifToken, elseToken )
////
//// ELASTIC_APM_IF_VA_ARGS_EMPTY_ELSE
////
////////////////////////////////////////////////////////////////////////////////

#define ELASTIC_APM_ENUM_NAMES_ARRAY_PAIR( enumElement ) [enumElement] = ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_PP_STRINGIZE( enumElement ) )
