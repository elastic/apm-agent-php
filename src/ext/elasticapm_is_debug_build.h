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

#ifndef ELASTICAPM_IS_DEBUG_BUILD_01
#   ifdef NDEBUG
#       define ELASTICAPM_IS_DEBUG_BUILD_01 0
#   else
#       define ELASTICAPM_IS_DEBUG_BUILD_01 1
#   endif
#endif
