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

#include "basic_macros.h"

int perTestDefaultSetup( void** testFixtureState );
int perTestDefaultTeardown( void** testFixtureState );
ELASTICAPM_SUPPRESS_UNUSED( perTestDefaultSetup );
ELASTICAPM_SUPPRESS_UNUSED( perTestDefaultTeardown );
