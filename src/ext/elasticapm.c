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

#ifdef HAVE_CONFIG_H
# include "config.h"
#endif

#include "php.h"
#include "php_ini.h"
#include "ext/standard/info.h"
#include "ext/standard/php_mt_rand.h"
#include "Zend/zend_exceptions.h"
#include "ext/spl/spl_exceptions.h"
#include "php_elasticapm.h"

// external libraries
#include <stdbool.h>
#include <curl/curl.h>
#include <sys/sysinfo.h>
#ifndef PHP_WIN32
#include "cpu_usage.h"
#endif

/* gettimeofday */
#ifdef PHP_WIN32
# include "win32/time.h"
#else
# include <sys/time.h>
#endif

/* For compatibility with older PHP versions */
#ifndef ZEND_PARSE_PARAMETERS_NONE
#define ZEND_PARSE_PARAMETERS_NONE() \
	ZEND_PARSE_PARAMETERS_START(0, 0) \
	ZEND_PARSE_PARAMETERS_END()
#endif

#define MICRO_IN_SEC 1000000.00
#define MILLI_IN_SEC 1000.00

ZEND_DECLARE_MODULE_GLOBALS(elasticapm);

static const char JSON_METADATA[] = "{\"metadata\":{\"process\":{\"pid\":%d},\"service\":{\"name\":\"%s\",\"language\":{\"name\":\"php\"},\"agent\":{\"version\":\"%s\",\"name\":\"apm-agent-php\"}}}}";
static const char JSON_TRANSACTION[] = "{\"transaction\":{\"name\":\"%s\",\"trace_id\":\"%s\",\"id\": \"%s\", \"type\": \"%s\", \"duration\": %.3f, \"timestamp\": %ld, \"result\": \"0\", \"context\": null, \"spans\": null, \"sampled\": null, \"span_count\": {\"started\": 0}}}";
static const char JSON_METRICSET[] = "{\"metricset\":{\"samples\":{\"system.cpu.total.norm.pct\":{\"value\":%.2f},\"system.process.cpu.total.norm.pct\":{\"value\":%.2f},\"system.memory.actual.free\":{\"value\":%ld},\"system.memory.total\":{\"value\":%ld},\"system.process.memory.size\":{\"value\":%ld},\"system.process.memory.rss.bytes\":{\"value\":%ld}},\"timestamp\":%ld}}";

/* {{{ PHP_RINIT_FUNCTION
 */
PHP_RINIT_FUNCTION(elasticapm)
{
#if defined(ZTS) && defined(COMPILE_DL_ELASTICAPM)
	ZEND_TSRMLS_CACHE_UPDATE();
#endif
	gettimeofday(&GA(start_time), NULL);

	// Generate random transaction_id and trace_id
	sprintf(GA(transaction_id), "%x%x", php_mt_rand(), php_mt_rand());
	sprintf(GA(trace_id), "%x%x%x%x", php_mt_rand(), php_mt_rand(), php_mt_rand(), php_mt_rand());

	// Get CPU usage and CPU process usage
#ifndef PHP_WIN32
	read_cpu(&GA(cpu_user), &GA(cpu_user_low), &GA(cpu_sys), &GA(cpu_idle));
	read_cpu_process(getpid(), &GA(cpu_process_user), &GA(cpu_process_user_low), &GA(cpu_process_sys), &GA(cpu_process_idle));
#endif

	return SUCCESS;
}
/* }}} */

#define FETCH_HTTP_GLOBALS(name) (tmp = &PG(http_globals)[TRACK_VARS_##name])
#define zend_is_auto_global_compat(name) (zend_is_auto_global_str(ZEND_STRL((name))))
#define REGISTER_INFO(name, dest, type) \
	if ((APM_RD(dest) = zend_hash_str_find(Z_ARRVAL_P(tmp), name, sizeof(name) - 1)) && (Z_TYPE_P(APM_RD(dest)) == (type))) { \
		APM_RD(dest##_found) = 1; \
	}

// Debug for printing cURL response
void function_pt(void *ptr, size_t size, size_t nmemb, void *stream){
    printf("%d\n", atoi(ptr));
}

/* {{{ PHP_RSHUTDOWN_FUNCTION
 */
PHP_RSHUTDOWN_FUNCTION(elasticapm)
{
	CURL *curl;
    CURLcode result;
	zval *tmp;
	struct timeval end_time;
	struct curl_slist *chunk = NULL;
	pid_t process_id;
	double cpu_usage, cpu_process_usage, duration;

	if (!GA(enable)) {
		return SUCCESS;
	}

	gettimeofday(&end_time, NULL);
	// Get execution time (duration) in ms
	duration = (end_time.tv_sec + end_time.tv_usec / MICRO_IN_SEC) -
		(GA(start_time).tv_sec + GA(start_time).tv_usec / MICRO_IN_SEC);

	zend_is_auto_global_compat("_SERVER");
	if (FETCH_HTTP_GLOBALS(SERVER)) {
		REGISTER_INFO("REQUEST_URI", uri, IS_STRING);
		REGISTER_INFO("HTTP_HOST", host, IS_STRING);
		REGISTER_INFO("HTTP_REFERER", referer, IS_STRING);
		REGISTER_INFO("REQUEST_TIME", ts, IS_LONG);
		REGISTER_INFO("SCRIPT_FILENAME", script, IS_STRING);
		REGISTER_INFO("REQUEST_METHOD", method, IS_STRING);
		REGISTER_INFO("REMOTE_ADDR", ip, IS_STRING);
	}

  	/* In windows, this will init the winsock stuff */
  	curl_global_init(CURL_GLOBAL_ALL);

  	/* get a curl handle */
  	curl = curl_easy_init();
  	if(curl) {
		// Metadata
		process_id = getpid();
		char *json_metadata = emalloc(sizeof(char) * 1024);
		if (strlen(INI_STR("elasticapm.service_name")) <= 0) {
			zend_throw_exception(spl_ce_RuntimeException, "You need to specify a service name in elasticapm.service_name", 0 TSRMLS_CC);
		}
		sprintf(json_metadata, JSON_METADATA, process_id, INI_STR("elasticapm.service_name"), PHP_ELASTICAPM_VERSION);

		// Transaction
		char *json_transaction = emalloc(sizeof(char) * 1024);
		int64_t timestamp = (int64_t) end_time.tv_sec * MICRO_IN_SEC + (int64_t) end_time.tv_usec;

		char *transaction_type = "script";
		char *transaction_name = emalloc(sizeof(char) * 1024);

		// if HTTP method exists it is a HTTP request
		if (APM_RD(method_found)) {
			transaction_type = "request";
			sprintf(transaction_name, "%s %s", APM_RD_STRVAL(method), APM_RD_STRVAL(uri));
		} else {
			sprintf(transaction_name, "%s", APM_RD_STRVAL(script));
		}
		sprintf(json_transaction, JSON_TRANSACTION, transaction_name, GA(transaction_id), GA(trace_id), transaction_type, duration, timestamp);

#ifdef PHP_WIN32
		cpu_usage = 0;
		cpu_process_usage = 0;
#else
		cpu_usage = get_cpu_usage(GA(cpu_user), GA(cpu_user_low), GA(cpu_sys), GA(cpu_idle));
		cpu_process_usage = get_cpu_process_usage(process_id, GA(cpu_process_user), GA(cpu_process_user_low), GA(cpu_process_sys), GA(cpu_process_idle));
#endif

		struct sysinfo info;
  		sysinfo(&info);

		// Metricset
		char *json_metricset = emalloc(sizeof(char) * 1024);
		sprintf(
			json_metricset,
			JSON_METRICSET,
			cpu_usage,							 // system.cpu.total.norm.pct
			cpu_process_usage, 					 // system.process.cpu.total.norm.pct
			info.freeram, 						 // system.memory.actual.free
			info.totalram,						 // system.memory.total
			zend_memory_peak_usage(0 TSRMLS_CC), // system.process.memory.size
			zend_memory_peak_usage(1 TSRMLS_CC), // system.process.memory.rss.bytes
			timestamp
		);

		// Body in ndjson format
		char *body = emalloc(sizeof(char) * (strlen(json_metricset) + strlen(json_transaction) + strlen(json_metadata) + 3));
		sprintf(body, "%s\n%s\n%s\n", json_metadata, json_transaction, json_metricset);

		curl_easy_setopt(curl, CURLOPT_POST, 1L);
		curl_easy_setopt(curl, CURLOPT_POSTFIELDS, body);

		// Authorization with secret token if present
		if (strlen(INI_STR("elasticapm.secret_token")) > 0) {
			char *auth = emalloc(sizeof(char) * 256);
			sprintf(auth, "Authorization: Bearer %s", INI_STR("elasticapm.secret_token"));
			chunk = curl_slist_append(chunk, auth);
			efree(auth);
		}
    	chunk = curl_slist_append(chunk, "Content-Type: application/x-ndjson");
    	curl_easy_setopt(curl, CURLOPT_HTTPHEADER, chunk);

		// User agent
		char *useragent = emalloc(sizeof(char) * 100);
		sprintf(useragent, "apm-agent-php/%s", PHP_ELASTICAPM_VERSION);
		curl_easy_setopt(curl, CURLOPT_USERAGENT, useragent);

		char *url = emalloc(sizeof(char)* 256);
		sprintf(url, "%s/intake/v2/events", INI_STR("elasticapm.host"));
    	curl_easy_setopt(curl, CURLOPT_URL, url);
		curl_easy_setopt(curl, CURLOPT_WRITEFUNCTION, function_pt);

	    result = curl_easy_perform(curl);
	    if(result != CURLE_OK) {
	    	fprintf(stderr, "curl_easy_perform() failed: %s\n", curl_easy_strerror(result));
		}
		long response_code;
    	curl_easy_getinfo(curl, CURLINFO_RESPONSE_CODE, &response_code);
		php_printf("Response code: %ld\n", response_code);
		curl_easy_cleanup(curl);

		efree(url);
		efree(useragent);
		efree(body);
		efree(transaction_name);
		efree(json_metadata);
		efree(json_transaction);
		efree(json_metricset);
  	}
  	curl_global_cleanup();
	return SUCCESS;
}
/* }}} */

/* {{{ PHP_MINFO_FUNCTION
 */
PHP_MINFO_FUNCTION(elasticapm)
{
	php_info_print_table_start();
	php_info_print_table_header(2, "Elastic APM agent", "enabled");
	php_info_print_table_row(2, "Version", PHP_ELASTICAPM_VERSION);
	php_info_print_table_end();

	DISPLAY_INI_ENTRIES();
}
/* }}} */

PHP_INI_BEGIN()
	// Disable by default (to prevent sending HTTP request to APM server)
	STD_PHP_INI_ENTRY("elasticapm.enable", "0", PHP_INI_ALL, OnUpdateBool, enable, zend_elasticapm_globals, elasticapm_globals)

    STD_PHP_INI_ENTRY("elasticapm.host", "http://localhost:8200", PHP_INI_ALL, OnUpdateString, host, zend_elasticapm_globals, elasticapm_globals)
	STD_PHP_INI_ENTRY("elasticapm.secret_token", "", PHP_INI_ALL, OnUpdateString, secret_token, zend_elasticapm_globals, elasticapm_globals)
	STD_PHP_INI_ENTRY("elasticapm.service_name", "", PHP_INI_ALL, OnUpdateString, service_name, zend_elasticapm_globals, elasticapm_globals)
PHP_INI_END()

PHP_MINIT_FUNCTION(elasticapm)
{
    REGISTER_INI_ENTRIES();

    return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(elasticapm)
{
    UNREGISTER_INI_ENTRIES();

    return SUCCESS;
}

PHP_FUNCTION(elasticapm_get_transaction_id)
{
	char *id = emalloc(sizeof(char) * strlen(GA(transaction_id)));
	sprintf(id, "%s", GA(transaction_id));
	RETURN_STRING(id);
}

PHP_FUNCTION(elasticapm_get_trace_id)
{
	char *id = emalloc(sizeof(char) * strlen(GA(trace_id)));
	sprintf(id, "%s", GA(trace_id));
	RETURN_STRING(id);
}

static const zend_function_entry elasticapm_functions[] =
{
    PHP_FE(elasticapm_get_transaction_id, NULL)
	PHP_FE(elasticapm_get_trace_id, NULL)
	PHP_FE_END
};

/* {{{ elasticapm_module_entry
 */
zend_module_entry elasticapm_module_entry = {
	STANDARD_MODULE_HEADER,
	"elasticapm",					/* Extension name */
	elasticapm_functions,			/* zend_function_entry */
	PHP_MINIT(elasticapm),		    /* PHP_MINIT - Module initialization */
	PHP_MSHUTDOWN(elasticapm),		/* PHP_MSHUTDOWN - Module shutdown */
	PHP_RINIT(elasticapm),			/* PHP_RINIT - Request initialization */
	PHP_RSHUTDOWN(elasticapm),		/* PHP_RSHUTDOWN - Request shutdown */
	PHP_MINFO(elasticapm),			/* PHP_MINFO - Module info */
	PHP_ELASTICAPM_VERSION,		    /* Version */
	PHP_MODULE_GLOBALS(elasticapm), /* PHP_MODULE_GLOBALS */
	NULL,                           /* PHP_GINIT */
	NULL,		                    /* PHP_GSHUTDOWN */
	NULL,
	STANDARD_MODULE_PROPERTIES_EX
};
/* }}} */

#ifdef COMPILE_DL_ELASTICAPM
# ifdef ZTS
ZEND_TSRMLS_CACHE_DEFINE()
# endif
ZEND_GET_MODULE(elasticapm)
#endif
