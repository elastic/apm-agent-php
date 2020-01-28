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
#include <SAPI.h>
#include "ext/standard/info.h"
#include "Zend/zend_exceptions.h"
#include "ext/spl/spl_exceptions.h"
#include "ext/standard/php_mt_rand.h"
#include "php_elasticapm.h"

// external libraries
#include <stdbool.h>
#include <curl/curl.h>
#include <sys/sysinfo.h>
#include "php_error.h"
#include "log.h"
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

static const char JSON_METADATA[] = "{\"metadata\":{\"process\":{\"pid\":%d},\"service\":{\"name\":\"%s\",\"language\":{\"name\":\"php\"},\"agent\":{\"version\":\"%s\",\"name\":\"apm-agent-php\"}}}}\n";
static const char JSON_TRANSACTION[] = "{\"transaction\":{\"name\":\"%s\",\"trace_id\":\"%s\",\"id\": \"%s\", \"type\": \"%s\", \"duration\": %.3f, \"timestamp\": %ld, \"result\": \"0\", \"context\": null, \"spans\": null, \"sampled\": null, \"span_count\": {\"started\": 0}}}\n";
static const char JSON_METRICSET[] = "{\"metricset\":{\"samples\":{\"system.cpu.total.norm.pct\":{\"value\":%.2f},\"system.process.cpu.total.norm.pct\":{\"value\":%.2f},\"system.memory.actual.free\":{\"value\":%ld},\"system.memory.total\":{\"value\":%ld},\"system.process.memory.size\":{\"value\":%ld},\"system.process.memory.rss.bytes\":{\"value\":%ld}},\"timestamp\":%ld}}\n";
static const char JSON_ERROR[] = "{\"error\":{\"timestamp\":%ld,\"id\":\"%s\",\"parent_id\":\"%s\",\"trace_id\":\"%s\",\"exception\":{\"code\":%d,\"message\":\"%s\",\"type\":\"%s\",\"stacktrace\":[{\"filename\":\"%s\",\"lineno\":%d}]},\"log\":{\"level\":\"%s\",\"logger_name\":\"PHP\",\"message\":\"%s\"}}}\n";
static const char JSON_EXCEPTION[] = "{\"error\":{\"timestamp\":%ld,\"id\":\"%s\",\"parent_id\":\"%s\",\"trace_id\":\"%s\",\"exception\":{\"code\":%ld,\"message\":\"%s\",\"type\":\"%s\",\"stacktrace\":[{\"filename\":\"%s\",\"lineno\":%ld}]}}}\n";

// Original error handler
void (*original_zend_error_cb)(int type, const char *error_filename, const uint error_lineno, const char *format, va_list args);

// Exception handler
static void (*original_zend_throw_exception_hook)(zval *ex);
void elastic_throw_exception_hook(zval *exception);

// Log response
static size_t log_response(void *ptr, size_t size, size_t nmemb, char *response);

// Generate a random hex string of len characters
static char *random_hex(int len)
{
    char *result = malloc(sizeof(char)*(len+1));
    for (int i = 0; i < len; i += 2)
    {
        sprintf(result+i, "%02lx", php_mt_rand_range(0,255));
    }
    result[len] = '\0';
    return result;
}

/* {{{ PHP_RINIT_FUNCTION
 */
PHP_RINIT_FUNCTION(elasticapm)
{

#if defined(ZTS) && defined(COMPILE_DL_ELASTICAPM)
	ZEND_TSRMLS_CACHE_UPDATE();
#endif

	gettimeofday(&GA(start_time), NULL);

	GA(transaction_id) = random_hex(16);
	GA(trace_id) = random_hex(32);

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

static size_t log_response(void *ptr, size_t size, size_t nmemb, char *response){
	log_debug("Reponse body: %s", ptr);
	return size * nmemb;
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
	FILE *log_file;
	char *body, *json_error, *json_exception;

	if (!GA(enable)) {
	 	return SUCCESS;
	}

	if (strlen(GA(service_name)) == 0) {
	 	zend_throw_exception(spl_ce_RuntimeException, "You need to specify a service name in elasticapm.service_name", 0 TSRMLS_CC);
	}

	gettimeofday(&end_time, NULL);
	// Get execution time (duration) in ms
	duration = ((end_time.tv_sec + end_time.tv_usec / MICRO_IN_SEC) -
		(GA(start_time).tv_sec + GA(start_time).tv_usec / MICRO_IN_SEC)) * 1000;

	zend_is_auto_global_compat("_SERVER");
	if (FETCH_HTTP_GLOBALS(SERVER)) {
		REGISTER_INFO("REQUEST_URI", uri, IS_STRING);
		REGISTER_INFO("HTTP_HOST", host, IS_STRING);
		REGISTER_INFO("HTTP_REFERER", referer, IS_STRING);
		REGISTER_INFO("REQUEST_TIME", ts, IS_LONG);
		REGISTER_INFO("SCRIPT_FILENAME", script, IS_STRING);
		REGISTER_INFO("REQUEST_METHOD", method, IS_STRING);
		REGISTER_INFO("REMOTE_ADDR", ip, IS_STRING);
		REGISTER_INFO("PWD", path, IS_STRING);
	}

  	/* get a curl handle */
  	curl = curl_easy_init();
  	if(curl && strlen(GA(host))>0) {
		// body
		char *body = emalloc(sizeof(char) * 102400); // max size 100 Kb

		// Metadata
		process_id = getpid();
		char *json_metadata = emalloc(sizeof(char) * 1024);
		sprintf(json_metadata, JSON_METADATA, process_id, GA(service_name), PHP_ELASTICAPM_VERSION);
		strcpy(body, json_metadata);
		efree (json_metadata);

		// Transaction
		char *json_transaction = emalloc(sizeof(char) * 1024);
		int64_t timestamp = (int64_t) GA(start_time).tv_sec * MICRO_IN_SEC + (int64_t) GA(start_time).tv_usec;

		char transaction_type[8];
		char *transaction_name = emalloc(sizeof(char) * 1024);

		// if HTTP method exists it is a HTTP request
		if (APM_RD(method_found)) {
			sprintf(transaction_type, "%s", "request");
			sprintf(transaction_name, "%s %s", APM_RD_STRVAL(method), APM_RD_STRVAL(uri));
		} else {
			sprintf(transaction_type, "%s", "script");
			sprintf(transaction_name, "%s", APM_RD_STRVAL(script));
		}
		sprintf(json_transaction, JSON_TRANSACTION, transaction_name, GA(transaction_id), GA(trace_id), transaction_type, duration, timestamp);
		strcat(body, json_transaction);
		efree(json_transaction);
		efree(transaction_name);

#ifdef PHP_WIN32
		cpu_usage = 0;
		cpu_process_usage = 0;
#else
		cpu_usage = get_cpu_usage(GA(cpu_user), GA(cpu_user_low), GA(cpu_sys), GA(cpu_idle));
		cpu_process_usage = get_cpu_process_usage(process_id, GA(cpu_process_user), GA(cpu_process_user_low), GA(cpu_process_sys), GA(cpu_process_idle));
#endif

		struct sysinfo info;
  		sysinfo(&info);

		int64_t timestamp_metricset = (int64_t) end_time.tv_sec * MICRO_IN_SEC + (int64_t) end_time.tv_usec;

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
			timestamp_metricset
		);
		strcat(body, json_metricset);
		efree(json_metricset);

		// Errors
		if (GA(errors)) {
			strcat(body, GA(errors));
		}
		// Exception
		if (GA(exceptions)) {
			strcat(body, GA(exceptions));
		}

		/* Initialize the log file */
		if (strlen(GA(log))>0) {
			log_file = fopen(GA(log), "a");
			if (log_file == NULL) {
				zend_throw_exception(spl_ce_RuntimeException, "Cannot access the file specified in elasticapm.log", 0 TSRMLS_CC);
			}
			log_set_fp(log_file);
			log_set_quiet(1);
			log_set_level(GA(log_level));

			// TODO: check how to set lock and level
			//log_set_lock(1);
		}
		curl_easy_setopt(curl, CURLOPT_POST, 1L);
		curl_easy_setopt(curl, CURLOPT_POSTFIELDS, body);
		curl_easy_setopt(curl, CURLOPT_WRITEFUNCTION, log_response);
		if (strlen(GA(log))>0) {
			log_debug("Request body: %s", body);
		}

		// Authorization with secret token if present
		if (strlen(GA(secret_token)) > 0) {
			char *auth = emalloc(sizeof(char) * 256);
			sprintf(auth, "Authorization: Bearer %s", GA(secret_token));
			chunk = curl_slist_append(chunk, auth);
			efree(auth);
		}
    	chunk = curl_slist_append(chunk, "Content-Type: application/x-ndjson");
    	curl_easy_setopt(curl, CURLOPT_HTTPHEADER, chunk);

		// User agent
		char *useragent = emalloc(sizeof(char) * 100);
		sprintf(useragent, "elasticapm-php/%s", PHP_ELASTICAPM_VERSION);
		curl_easy_setopt(curl, CURLOPT_USERAGENT, useragent);

		char *url = emalloc(sizeof(char)* 256);
		sprintf(url, "%s/intake/v2/events", GA(host));
    	curl_easy_setopt(curl, CURLOPT_URL, url);

	    result = curl_easy_perform(curl);
		if (strlen(GA(log))>0) {
			if(result != CURLE_OK) {
				log_error("%s %s", GA(host), curl_easy_strerror(result));
			} else {
				long response_code;
				curl_easy_getinfo(curl, CURLINFO_RESPONSE_CODE, &response_code);
				log_debug("Response HTTP code: %ld", response_code);
			}
		}

		curl_easy_cleanup(curl);
		if (strlen(GA(log))>0) {
			fclose(log_file);
		}
		efree(body);
		efree(url);
		efree(useragent);
  	}
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

ZEND_INI_DISP(hide_secret)
{
    char mask[3] = "***";
	char no_value[15] = "<i>no value</i>";

	if ((type == ZEND_INI_DISPLAY_ORIG && ini_entry->modified && ini_entry->orig_value) ||
		(type == ZEND_INI_DISPLAY_ACTIVE && ini_entry->value)) {
		php_write(mask, 3);
	} else {
		if (!sapi_module.phpinfo_as_text) {
			php_write(no_value, 15);
		}
	}
}

PHP_INI_BEGIN()
	STD_PHP_INI_BOOLEAN("elasticapm.enable", "0", PHP_INI_ALL, OnUpdateBool, enable, zend_elasticapm_globals, elasticapm_globals)
    STD_PHP_INI_ENTRY("elasticapm.host", "localhost:8200", PHP_INI_ALL, OnUpdateString, host, zend_elasticapm_globals, elasticapm_globals)
	STD_PHP_INI_ENTRY_EX("elasticapm.secret_token", "", PHP_INI_ALL, OnUpdateString, secret_token, zend_elasticapm_globals, elasticapm_globals, hide_secret)
	STD_PHP_INI_ENTRY("elasticapm.service_name", "", PHP_INI_ALL, OnUpdateString, service_name, zend_elasticapm_globals, elasticapm_globals)
	STD_PHP_INI_ENTRY("elasticapm.log", "", PHP_INI_ALL, OnUpdateString, log, zend_elasticapm_globals, elasticapm_globals)
	STD_PHP_INI_ENTRY("elasticapm.log_level", "0", PHP_INI_ALL, OnUpdateLong, log_level, zend_elasticapm_globals, elasticapm_globals)
PHP_INI_END()

// Elastic error handler
void elastic_error_cb(int type, const char *error_filename, const uint error_lineno, const char *format, va_list args)
{
	va_list args_copy;
	char *msg;
	char *error_id;
	struct timeval time;

	va_copy(args_copy, args);
	vspprintf(&msg, 0, format, args_copy);
	va_end(args_copy);

	gettimeofday(&time, NULL);
	int64_t timestamp = (int64_t) time.tv_sec * MICRO_IN_SEC + (int64_t) time.tv_usec;

	// Generate random error_id (128 bit in hex format)
	error_id = random_hex(32);

	char *json_error = emalloc(sizeof(char) * 1024);
	sprintf(
		json_error,
		JSON_ERROR,
		timestamp,
		error_id,
		GA(transaction_id),
		GA(trace_id),
		type,
		msg,
		get_php_error_name(type),
		error_filename,
		error_lineno,
		get_php_error_name(type),
		msg
	);
	if (GA(errors)) {
		strcat(GA(errors), json_error);
	} else {
		GA(errors) = emalloc(sizeof(char) * 10240); // 10 Kb
		strcpy(GA(errors), json_error);
	}
	efree(json_error);

    original_zend_error_cb(type, error_filename, error_lineno, format, args);
}

// Elastic exception handler
void elastic_throw_exception_hook(zval *exception)
{
	zval *code, *message, *file, *line;
	zval rv;
	zend_class_entry *default_ce;
	zend_string *classname;
	char *exception_id;
	struct timeval time;

	default_ce = Z_OBJCE_P(exception);

	classname = Z_OBJ_HANDLER_P(exception, get_class_name)(Z_OBJ_P(exception));
	code = zend_read_property(default_ce, exception, "code", sizeof("code")-1, 0, &rv);
	message = zend_read_property(default_ce, exception, "message", sizeof("message")-1, 0, &rv);
	file = zend_read_property(default_ce, exception, "file", sizeof("file")-1, 0, &rv);
	line = zend_read_property(default_ce, exception, "line", sizeof("line")-1, 0, &rv);

	// Generate random exception_id (128 bit in hex format)
	exception_id = random_hex(32);

	gettimeofday(&time, NULL);
	int64_t timestamp = (int64_t) time.tv_sec * MICRO_IN_SEC + (int64_t) time.tv_usec;

	char *json_exception = emalloc(sizeof(char) * 1024);
	sprintf(
		json_exception,
		JSON_EXCEPTION,
		timestamp,
		exception_id,
		GA(transaction_id),
		GA(trace_id),
		Z_LVAL_P(code),
		Z_STRVAL_P(message),
		ZSTR_VAL(classname),
		Z_STRVAL_P(file),
		Z_LVAL_P(line)
	);
	if (GA(exceptions)) {
		strcat(GA(exceptions), json_exception);
	} else {
		GA(exceptions) = emalloc(sizeof(char) * 10240); // 10 Kb
		strcpy(GA(exceptions), json_exception);
	}
	efree(json_exception);

    if (original_zend_throw_exception_hook != NULL) {
        original_zend_throw_exception_hook(exception);
    }
}

PHP_MINIT_FUNCTION(elasticapm)
{
    REGISTER_INI_ENTRIES();
	
	// Error handler
	original_zend_error_cb = zend_error_cb;
	zend_error_cb = elastic_error_cb;

	// Exception handler
	original_zend_throw_exception_hook = zend_throw_exception_hook;
    zend_throw_exception_hook = elastic_throw_exception_hook;
	
	/* In windows, this will init the winsock stuff */
	curl_global_init(CURL_GLOBAL_ALL);
	
    return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(elasticapm)
{
	UNREGISTER_INI_ENTRIES();

	zend_error_cb = original_zend_error_cb;
	curl_global_cleanup();

    return SUCCESS;
}

PHP_FUNCTION(elasticapm_get_transaction_id)
{
	RETURN_STRING(GA(transaction_id));
}

PHP_FUNCTION(elasticapm_get_trace_id)
{
	RETURN_STRING(GA(trace_id));
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
	NULL, 					        /* PHP_GINIT */
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
