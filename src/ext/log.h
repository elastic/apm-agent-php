/**
 * Copyright (c) 2017 rxi
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the MIT license. See `log.c` for details.
 */

#pragma once

#include <stdio.h>
#include <stdarg.h>

#define LOG_VERSION "0.1.0"

typedef void (*log_LockFn)(void *udata, int lock);

enum { LOG_APM_TRACE, LOG_APM_DEBUG, LOG_APM_INFO, LOG_APM_WARN, LOG_APM_ERROR, LOG_APM_FATAL };

#define log_trace(...) log_log(LOG_APM_TRACE, __FILE__, __LINE__, __VA_ARGS__)
#define log_debug(...) log_log(LOG_APM_DEBUG, __FILE__, __LINE__, __VA_ARGS__)
#define log_info(...)  log_log(LOG_APM_INFO,  __FILE__, __LINE__, __VA_ARGS__)
#define log_warn(...)  log_log(LOG_APM_WARN,  __FILE__, __LINE__, __VA_ARGS__)
#define log_error(...) log_log(LOG_APM_ERROR, __FILE__, __LINE__, __VA_ARGS__)
#define log_fatal(...) log_log(LOG_APM_FATAL, __FILE__, __LINE__, __VA_ARGS__)

void log_set_udata(void *udata);
void log_set_lock(log_LockFn fn);
void log_set_fp(FILE *fp);
void log_set_level(int level);
void log_set_quiet(int enable);

void log_log(int level, const char *file, int line, const char *fmt, ...);


#ifdef PHP_WIN32
#   ifdef ELASTICAPM_DEV_WINDOWS
#       define LOG_FUNCTION_ENTRY() OutputDebugString( "ElasticAPM: DUMMY for LOG_FUNCTION_ENTRY" )
#       define LOG_FUNCTION_EXIT() OutputDebugString( "ElasticAPM: DUMMY for LOG_FUNCTION_EXIT" )
#       define LOG_FUNCTION_EXIT_MSG( msg ) OutputDebugString( "ElasticAPM: DUMMY for LOG_FUNCTION_EXIT_MSG" )
#       define LOG_MSG( msg ) OutputDebugString( "ElasticAPM: DUMMY for LOG_MSG" )
#   else
#       define LOG_FUNCTION_ENTRY() OutputDebugString( "ElasticAPM: " __FUNCTION__ ": Entered" )
#       define LOG_FUNCTION_EXIT() OutputDebugString( "ElasticAPM: " __FUNCTION__ ": Exiting" )
#       define LOG_FUNCTION_EXIT_MSG( msg ) OutputDebugString( "ElasticAPM: " __FUNCTION__ ": Exiting: " msg )
#       define LOG_MSG( msg ) OutputDebugString( "ElasticAPM: " __FUNCTION__ ": " msg )
#   endif
#else
#   define LOG_FUNCTION_ENTRY() /**/
#   define LOG_FUNCTION_EXIT() /**/
#   define LOG_FUNCTION_EXIT_MSG( msg ) /**/
#   define LOG_MSG( msg ) /**/
#endif
