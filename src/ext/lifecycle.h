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

#ifndef ELASTICAPM_LIFECYCLE_H
#define ELASTICAPM_LIFECYCLE_H

#include "ResultCode.h"


ResultCode elasticApmModuleInit( int type, int moduleNumber );

ResultCode elasticApmModuleShutdown( int type, int moduleNumber );

ResultCode elasticApmRequestInit();

ResultCode elasticApmRequestShutdown();

#endif /* #ifndef ELASTICAPM_LIFECYCLE_H */
