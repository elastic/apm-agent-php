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

#include "ResultCode.h"


void elasticApmModuleInit( int type, int moduleNumber );

void elasticApmModuleShutdown( int type, int moduleNumber );

void elasticApmRequestInit();

void elasticApmRequestShutdown();
