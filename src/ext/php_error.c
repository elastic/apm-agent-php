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

#include "php_error.h"

#include <zend_errors.h>

const char* get_php_error_name( int code )
{
    switch ( code )
    {
        case E_ERROR:
            return "E_ERROR";
        case E_WARNING:
            return "E_WARNING";
        case E_PARSE:
            return "E_PARSE";
        case E_NOTICE:
            return "E_NOTICE";
        case E_CORE_ERROR:
            return "E_CORE_ERROR";
        case E_CORE_WARNING:
            return "E_CORE_WARNING";
        case E_COMPILE_ERROR:
            return "E_COMPILE_ERROR";
        case E_COMPILE_WARNING:
            return "E_COMPILE_WARNING";
        case E_USER_ERROR:
            return "E_USER_ERROR";
        case E_USER_WARNING:
            return "E_USER_WARNING";
        case E_USER_NOTICE:
            return "E_USER_NOTICE";
        case E_STRICT:
            return "E_STRICT";
        case E_RECOVERABLE_ERROR:
            return "E_RECOVERABLE_ERROR";
        case E_DEPRECATED:
            return "E_DEPRECATED";
        case E_USER_DEPRECATED:
            return "E_USER_DEPRECATED";
        default:
            return "UNDEFINED";
    }
}
