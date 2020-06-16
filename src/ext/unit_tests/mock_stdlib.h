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

/**
 * mockGetEnv is used in "ConfigManager.c"
 * via ELASTICAPM_GETENV_FUNC defined in unit tests' CMakeLists.txt
 *
 * mockGetEnv returns `char*` and not `const char*` on purpose
 * because real <stdlib.h> defines getenv as it's done below
 */
char* mockGetEnv( const char* name );
