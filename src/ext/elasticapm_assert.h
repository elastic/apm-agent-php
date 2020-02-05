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

#ifndef ELASTICAPM_ASSERT_H
#define ELASTICAPM_ASSERT_H

#include <assert.h>

#ifndef ELASTICAPM_ASSERT_ENABLED
#   ifdef NDEBUG
#       define ELASTICAPM_ASSERT_ENABLED 0
#   else
#       define ELASTICAPM_ASSERT_ENABLED 1
#   endif
#endif

#if defined( ELASTICAPM_ASSERT_ENABLED ) && ( ELASTICAPM_ASSERT_ENABLED == 1 )

enum ElasticApmAssertLevel
{
    elasticApmAssertLevel_O_1 = 1,
    elasticApmAssertLevel_O_n = 2
};

extern int g_currentElasticApmAssertLevel;

#define ELASTICAPM_ASSERT( cond ) ELASTICAPM_ASSERT_MSG( cond, NULL )
#define ELASTICAPM_ASSERT_MSG( cond, msg ) ELASTICAPM_ASSERT_IMPL( elasticApmAssertLevel_O_1, cond, msg )
#define ELASTICAPM_ASSERT_O_N( cond ) ELASTICAPM_ASSERT_O_N_MSG( cond, NULL )
#define ELASTICAPM_ASSERT_O_N_MSG( cond, msg ) ELASTICAPM_ASSERT_IMPL( elasticApmAssertLevel_O_N, cond, msg )

#define ELASTICAPM_ASSERT_IMPL( level, cond, msg ) \
    if ( g_currentElasticApmAssertLevel >= (level) ) \
        assert( cond )

#else /* #if defined( ELASTICAPM_ASSERT_ENABLED ) && ( ELASTICAPM_ASSERT_ENABLED == 1 ) */

#define ELASTICAPM_ASSERT( cond )
#define ELASTICAPM_ASSERT_MSG( cond, msg )
#define ELASTICAPM_ASSERT_O_N( cond )
#define ELASTICAPM_ASSERT_O_N_MSG( cond, msg )

#endif /* #if defined( ELASTICAPM_ASSERT_ENABLED ) && ( ELASTICAPM_ASSERT_ENABLED == 1 ) */

#define ELASTICAPM_IS_VALID_PTR( ptr ) ( (ptr) != NULL )
#define ASSERT_VALID_PTR( ptr ) ELASTICAPM_ASSERT( ELASTICAPM_IS_VALID_PTR( ptr ) )
#define ASSERT_VALID_OUT_PTR_TO_PTR( ptrToPtr ) ELASTICAPM_ASSERT( ELASTICAPM_IS_VALID_PTR( ptrToPtr ) && ( *(ptrToPtr) == NULL ) )
#define ASSERT_VALID_IN_PTR_TO_PTR( ptrToPtr ) ELASTICAPM_ASSERT( ELASTICAPM_IS_VALID_PTR( ptrToPtr ) && ELASTICAPM_IS_VALID_PTR( *(ptrToPtr) ) )

#endif /* #ifndef ELASTICAPM_ASSERT_H */
