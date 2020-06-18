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

#ifdef PHP_WIN32

#define	LOG_EMERG	1
#define	LOG_ALERT	1
#define	LOG_CRIT	1
#define	LOG_ERR		4
#define	LOG_WARNING	5
#define	LOG_NOTICE	6
#define	LOG_INFO	6
#define	LOG_DEBUG	6

#else // #ifdef PHP_WIN32

#include <syslog.h>

#endif // #ifdef PHP_WIN32
