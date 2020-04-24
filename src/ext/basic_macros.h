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

#define ELASTICAPM_UNUSED( var ) (void)(var)

#define ELASTICAPM_PP_STRINGIZE_IMPL( token ) #token
#define ELASTICAPM_PP_STRINGIZE( token ) ELASTICAPM_PP_STRINGIZE_IMPL( token )

#define ELASTICAPM_PP_CONCAT_IMPL( token1, token2 ) token1##token2
#define ELASTICAPM_PP_CONCAT( token1, token2 ) ELASTICAPM_PP_CONCAT_IMPL( token1, token2 )

#define ELASTICAPM_IS_IN_INCLUSIVE_RANGE( rangeBegin, x, rangeEnd ) ( ( (rangeBegin) <= (x) ) && ( (x) <= (rangeEnd) ) )

#define ELASTICAPM_IS_IN_END_EXCLUDED_RANGE( rangeBeginIncluded, x, rangeEndExcluded ) ( ( (rangeBeginIncluded) <= (x) ) && ( (x) < (rangeEndExcluded) ) )

#define ELASTICAPM_STATIC_ARRAY_SIZE( array ) ( ( sizeof( (array) ) ) / sizeof( (array)[ 0 ] ) )

#define ELASTICAPM_FOR_EACH_INDEX_START_END( indexVarType, indexVar, rangeStart, rangeExcludedEnd ) \
    for ( indexVarType indexVar = rangeStart ; (indexVar) < (rangeExcludedEnd) ; ++indexVar )

#define ELASTICAPM_FOR_EACH_INDEX_EX( indexVarType, indexVar, rangeSize ) \
    ELASTICAPM_FOR_EACH_INDEX_START_END( indexVarType, indexVar, 0, rangeSize )

#define ELASTICAPM_FOR_EACH_INDEX( indexVar, rangeSize ) ELASTICAPM_FOR_EACH_INDEX_EX( size_t, indexVar, rangeSize )

#define ELASTICAPM_REPEAT_N_TIMES( numberOfTimes ) \
    ELASTICAPM_FOR_EACH_INDEX_EX( size_t, ELASTICAPM_PP_CONCAT( elasticapm_repeat_n_times_index_var, __LINE__ ), numberOfTimes )
/**/

#define ELASTICAPM_FOR_EACH_BACKWARDS( indexVar, rangeSize ) \
    for \
    ( \
        /* init */ \
        size_t \
            elasticapm_for_each_backwards_number_of_elements_remaining = (rangeSize), \
            (indexVar) = (rangeSize) == 0 ? 0 : (rangeSize) - 1; \
        \
        /* stop condition */ \
        elasticapm_for_each_backwards_number_of_elements_remaining != 0 ; \
        \
        /* step */ \
            --elasticapm_for_each_backwards_number_of_elements_remaining, \
            (indexVar) = ( (indexVar) == 0 ) ? 0 : (indexVar) - 1 \
    )

#define ELASTICAPM_MIN( a, b ) ( ( (a) < (b) ) ? (a) : (b) )
#define ELASTICAPM_MAX( a, b ) ( ( (a) > (b) ) ? (a) : (b) )

#define ELASTICAPM_ZERO_STRUCT( structPtr ) memset( (structPtr), 0, sizeof( *(structPtr) ) )


#if ( ! defined( PHP_WIN32 ) ) || defined( ELASTICAPM_UNDER_IDE )
#   define ELASTICAPM_PRINTF_ATTRIBUTE( fmtPos, fmtArgsPos ) __attribute__ ( ( format ( printf, fmtPos, fmtArgsPos ) ) )
#else
#   define ELASTICAPM_PRINTF_ATTRIBUTE( fmtPos, fmtArgsPos )
#endif

#if ( ! defined( PHP_WIN32 ) ) || defined( ELASTICAPM_UNDER_IDE )
#   define ELASTICAPM_NO_RETURN_ATTRIBUTE __attribute__ ( ( noreturn ) )
#else
#   define ELASTICAPM_NO_RETURN_ATTRIBUTE
#endif


#define ELASTICAPM_PP_EXPAND( somePPTokenToExpand ) somePPTokenToExpand

//////////////////////////////////////////////////////////////////////////////
//
// ELASTICAPM_PP_VARIADIC_ARGS_COUNT
//

#ifdef _MSC_VER // Microsoft compilers

#   define ELASTICAPM_PP_VARIADIC_ARGS_COUNT_HELPER_AUGMENTER( ... ) unusedPPToken, __VA_ARGS__
#   define ELASTICAPM_PP_VARIADIC_ARGS_COUNT_HELPER( dummyArg_1, dummyArg_2, dummyArg_3, dummyArg_4, dummyArg_5, dummyArg_6, dummyArg_7, dummyArg_8, dummyArg_9, dummyArg_10, dummyArg_11, count, ... ) count
#   define ELASTICAPM_PP_VARIADIC_ARGS_COUNT_HELPER_EXPAND_ARGS( ... ) ELASTICAPM_PP_EXPAND( ELASTICAPM_PP_VARIADIC_ARGS_COUNT_HELPER( __VA_ARGS__, 10, 9, 8, 7, 6, 5, 4, 3, 2, 1, 0 ) )
#   define ELASTICAPM_PP_VARIADIC_ARGS_COUNT( ... ) ELASTICAPM_PP_VARIADIC_ARGS_COUNT_HELPER_EXPAND_ARGS( ELASTICAPM_PP_VARIADIC_ARGS_COUNT_HELPER_AUGMENTER( __VA_ARGS__ ) )

#else // Non-Microsoft compilers

#   define ELASTICAPM_PP_VARIADIC_ARGS_COUNT_HELPER( dummyArg_0, dummyArg_1, dummyArg_2, dummyArg_3, dummyArg_4, dummyArg_5, dummyArg_6, dummyArg_7, dummyArg_8, dummyArg_9, dummyArg_10, dummyArg_11, count, ... ) count
#   define ELASTICAPM_PP_VARIADIC_ARGS_COUNT( ... ) ELASTICAPM_PP_VARIADIC_ARGS_COUNT_HELPER( 0, ## __VA_ARGS__, 11, 10, 9, 8, 7, 6, 5, 4, 3, 2, 1, 0 )

#endif

//
// ELASTICAPM_PP_VARIADIC_ARGS_COUNT
//
//////////////////////////////////////////////////////////////////////////////

#ifdef ELASTICAPM_UNDER_IDE
#define ELASTICAPM_SUPPRESS_UNUSED( symbol ) \
    static void* ELASTICAPM_PP_CONCAT( g_ELASTICAPM_SUPPRESS_UNUSED_, ELASTICAPM_PP_CONCAT( __LINE__, ELASTICAPM_PP_CONCAT( _, __COUNTER__ ) ) ) = &( symbol )
#else
#define ELASTICAPM_SUPPRESS_UNUSED( symbol )
#endif

#define ELASTICAPM_NOOP_STATEMENT ((void)(0))

#define ELASTICAPM_FIELD_SIZEOF( StructType, field ) (sizeof( ((StructType*)NULL)->field ))

////////////////////////////////////////////////////////////////////////////////
////
//// ELASTICAPM_IF_VA_ARGS_EMPTY_ELSE
////
#define ELASTICAPM_IF_VA_ARGS_EMPTY_ELSE_0( ifToken, elseToken ) ifToken
#define ELASTICAPM_IF_VA_ARGS_EMPTY_ELSE_1( ifToken, elseToken ) elseToken
#define ELASTICAPM_IF_VA_ARGS_EMPTY_ELSE_2( ifToken, elseToken ) elseToken
#define ELASTICAPM_IF_VA_ARGS_EMPTY_ELSE_3( ifToken, elseToken ) elseToken
#define ELASTICAPM_IF_VA_ARGS_EMPTY_ELSE_4( ifToken, elseToken ) elseToken
#define ELASTICAPM_IF_VA_ARGS_EMPTY_ELSE_5( ifToken, elseToken ) elseToken
#define ELASTICAPM_IF_VA_ARGS_EMPTY_ELSE_6( ifToken, elseToken ) elseToken
#define ELASTICAPM_IF_VA_ARGS_EMPTY_ELSE_7( ifToken, elseToken ) elseToken
#define ELASTICAPM_IF_VA_ARGS_EMPTY_ELSE_8( ifToken, elseToken ) elseToken
#define ELASTICAPM_IF_VA_ARGS_EMPTY_ELSE_9( ifToken, elseToken ) elseToken
#define ELASTICAPM_IF_VA_ARGS_EMPTY_ELSE_10( ifToken, elseToken ) elseToken
#define ELASTICAPM_IF_VA_ARGS_EMPTY_ELSE( ifToken, elseToken, ... ) \
    ELASTICAPM_PP_EXPAND( ELASTICAPM_PP_CONCAT( ELASTICAPM_IF_VA_ARGS_EMPTY_ELSE_, ELASTICAPM_PP_VARIADIC_ARGS_COUNT( __VA_ARGS__ ) ) ) ( ifToken, elseToken )
////
//// ELASTICAPM_IF_VA_ARGS_EMPTY_ELSE
////
////////////////////////////////////////////////////////////////////////////////
