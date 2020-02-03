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

#include "elasticapm_assert.h"

#ifdef ELASTICAPM_ASSERT_ENABLED

#ifndef ELASTICAPM_ASSERT_DEFAULT_LEVEL
#   ifdef NDEBUG
#       define ELASTICAPM_ASSERT_DEFAULT_LEVEL elasticApmAssertLevel_O_1
#   else
#       define ELASTICAPM_ASSERT_DEFAULT_LEVEL elasticApmAssertLevel_O_n
#   endif
#endif

int g_currentElasticApmAssertLevel = ELASTICAPM_ASSERT_DEFAULT_LEVEL;

#endif
