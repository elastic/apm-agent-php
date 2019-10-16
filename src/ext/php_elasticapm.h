/*
   +----------------------------------------------------------------------+
   | Elastic APM agent for PHP                                            |
   +----------------------------------------------------------------------+
   | Copyright (c) 2019 Elasticsearch B.V                                 |
   +----------------------------------------------------------------------+
   | Elasticsearch B.V licenses this file under the Apache 2.0 License.   |
   | See the LICENSE file in the project root for more information.       |
   +----------------------------------------------------------------------+
   | Authors: Enrico Zimuel <enrico.zimuel@elastic.co>                    |
   |          Philip Krauss <philip.krauss@elastic.co>                    |
   +----------------------------------------------------------------------+
 */

#ifndef PHP_ELASTICAPM_H
# define PHP_ELASTICAPM_H

extern zend_module_entry elasticapm_module_entry;
# define phpext_elasticapm_ptr &elasticapm_module_entry

# define PHP_ELASTICAPM_VERSION "2.0.0alpha"

# if defined(ZTS) && defined(COMPILE_DL_ELASTICAPM)
ZEND_TSRMLS_CACHE_EXTERN()
# endif

PHP_FUNCTION(elasticapm_get_transaction_id);
PHP_FUNCTION(elasticapm_get_trace_id);

# define RD_DEF(var) zval *var; zend_bool var##_found;

typedef struct apm_request_data {
	RD_DEF(uri);
	RD_DEF(host);
	RD_DEF(ip);
	RD_DEF(referer);
	RD_DEF(ts);
	RD_DEF(script);
	RD_DEF(method);
	RD_DEF(path);
} apm_request_data;

ZEND_BEGIN_MODULE_GLOBALS(elasticapm)
	/* Structure used to store request data */
	apm_request_data request_data;
	char transaction_id[17];
	char trace_id[33];
	/* error */
	char error_id[33];
	char *error_msg;
	int error_code;
	int error_line;
	char *error_filename;
	struct timeval error_time;

	/* metrics */
	struct timeval start_time;
#ifndef PHP_WIN32
	unsigned long long cpu_user;
	unsigned long long cpu_user_low;
	unsigned long long cpu_sys;
	unsigned long long cpu_idle;
	unsigned long long cpu_process_user;
	unsigned long long cpu_process_user_low;
	unsigned long long cpu_process_sys;
	unsigned long long cpu_process_idle;
#endif
	/* ini settings */
	char *host;
	char *secret_token;
	char *service_name;
	char *log;
	zend_bool enable;
ZEND_END_MODULE_GLOBALS(elasticapm)

# define APM_RD(data) GA(request_data).data
# define APM_RD_STRVAL(var) Z_STRVAL_P(APM_RD(var))

#ifdef ZTS
#define GA(v) ZEND_MODULE_GLOBALS_ACCESSOR(elasticapm, v)
#else
#define GA(v) (elasticapm_globals.v)
#endif

#endif	/* PHP_ELASTICAPM_H */
