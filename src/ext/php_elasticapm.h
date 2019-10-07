/* elasticapm extension for PHP */

#ifndef PHP_ELASTICAPM_H
# define PHP_ELASTICAPM_H

extern zend_module_entry elasticapm_module_entry;
# define phpext_elasticapm_ptr &elasticapm_module_entry

# define PHP_ELASTICAPM_VERSION "2.0.0alpha"

# if defined(ZTS) && defined(COMPILE_DL_ELASTICAPM)
ZEND_TSRMLS_CACHE_EXTERN()
# endif

# define RD_DEF(var) zval *var; zend_bool var##_found;

typedef struct apm_request_data {
	RD_DEF(uri);
	RD_DEF(host);
	RD_DEF(ip);
	RD_DEF(referer);
	RD_DEF(ts);
	RD_DEF(script);
	RD_DEF(method);
} apm_request_data;

ZEND_BEGIN_MODULE_GLOBALS(elasticapm)
	/* Structure used to store request data */
	apm_request_data request_data;
	char transaction_id[16];
	char trace_id[32];

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
