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

#ifndef ELASTIC_APM_IS_DEBUG_BUILD_01
#   ifdef NDEBUG
#       define ELASTIC_APM_IS_DEBUG_BUILD_01 0
#   else
#       define ELASTIC_APM_IS_DEBUG_BUILD_01 1
#   endif
#endif
