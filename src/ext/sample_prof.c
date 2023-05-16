#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "php_ini.h"
#include "ext/standard/info.h"
#include "php_sample_prof.h"
#include "zend_exceptions.h"
#include "sample_prof_arginfo.h"

#include <pthread.h>

#define SAMPLE_PROF_DEFAULT_INTERVAL 1

/* On 64-bit this will give a 16 * 1MB allocation */
#define SAMPLE_PROF_DEFAULT_ALLOC (1 << 20)

ZEND_DECLARE_MODULE_GLOBALS(sample_prof)

static inline zend_bool sample_prof_end() {
    if (!SAMPLE_PROF_G->enabled) {
        return 0;
    }

    pthread_cancel(SAMPLE_PROF_G->thread_id);

    SAMPLE_PROF_G->enabled = 0;
    return 1;
}

static void *sample_prof_handler(void *data) {
    zend_sample_prof_globals *g = SAMPLE_PROF_G;

#ifdef ZTS
    volatile zend_executor_globals *eg = TSRMG_BULK(executor_globals_id, zend_executor_globals *);
#else
    volatile zend_executor_globals *eg = &executor_globals;
#endif

    retry_now:
    while (1) {
        volatile zend_execute_data *ex = eg->current_execute_data, *start_ex = ex;
        zend_function *func;
        const zend_op *opline;

        while (1) {
            zend_execute_data *prev;

            /* We're not executing code right now, try again later */
            if (!ex) {
                goto retry_later;
            }

            func = ex->func;
            opline = ex->opline;
            prev = ex->prev_execute_data;

            /* current_execute_data changed in the meantime, reload it */
            if (eg->current_execute_data != start_ex) {
                goto retry_now;
            }

            if (func && ZEND_USER_CODE(func->type)) {
                break;
            }

            ex = prev;
        }

        if (!opline) {
            goto retry_later;
        }

        g->entries[g->entries_num].filename = func->op_array.filename;
        g->entries[g->entries_num].lineno = opline->lineno;

        if (++g->entries_num == g->entries_allocated) {
            /* Doing a realloc within a signal handler is unsafe, end profiling */
            g->enabled = 0;
            break;
        }

        retry_later:
        usleep(g->interval_usec);
    }
    pthread_exit(NULL);
}

static void sample_prof_start(long interval_usec, size_t num_entries_alloc) {
    zend_sample_prof_globals *g = SAMPLE_PROF_G;

    /* Initialize data structures for entries */
    if (g->entries) {
        efree(g->entries);
    }

    g->interval_usec = interval_usec;
    g->entries_allocated = num_entries_alloc;
    g->entries_num = 0;
    g->entries = safe_emalloc(g->entries_allocated, sizeof(sample_prof_entry), 0);

    /* Register signal handler */
    if (pthread_create(&g->thread_id, NULL, sample_prof_handler, NULL)) {
        zend_throw_exception(NULL, "Could not register signal handler", 0);
        return;
    }

    g->enabled = 1;
}

PHP_FUNCTION(sample_prof_start) {
        zend_long interval_usec = 0;
        zend_long num_entries_alloc = 0;

        if (zend_parse_parameters(ZEND_NUM_ARGS(), "|ll", &interval_usec, &num_entries_alloc) == FAILURE) {
            return;
        }

        if (interval_usec < 0) {
            zend_throw_exception(NULL, "Number of microseconds can't be negative", 0);
            return;
        } else if (interval_usec == 0) {
            interval_usec = SAMPLE_PROF_DEFAULT_INTERVAL;
        }

        if (num_entries_alloc < 0) {
            zend_throw_exception(NULL, "Number of profiling can't be negative", 0);
            return;
        } else if (num_entries_alloc == 0) {
            num_entries_alloc = SAMPLE_PROF_DEFAULT_ALLOC;
        }

        sample_prof_start(interval_usec, num_entries_alloc);
}

PHP_FUNCTION(sample_prof_end) {
        if (zend_parse_parameters_none() == FAILURE) {
            return;
        }

        RETURN_BOOL(sample_prof_end());
}

PHP_FUNCTION(sample_prof_get_data) {
        zend_sample_prof_globals *g = SAMPLE_PROF_G;
        size_t entry_num;

        if (zend_parse_parameters_none() == FAILURE) {
            return;
        }

        array_init(return_value);

        for (entry_num = 0; entry_num < g->entries_num; ++entry_num) {
            sample_prof_entry *entry = &g->entries[entry_num];
            zend_string *filename = entry->filename;
            uint32_t lineno = entry->lineno;
            zval *lines, *num;

            lines = zend_hash_find(Z_ARR_P(return_value), filename);
            if (lines == NULL) {
                zval lines_zv;
                array_init(&lines_zv);
                lines = zend_hash_update(Z_ARR_P(return_value), filename, &lines_zv);
            }

            num = zend_hash_index_find(Z_ARR_P(lines), lineno);
            if (num == NULL) {
                zval num_zv;
                ZVAL_LONG(&num_zv, 0);
                num = zend_hash_index_update(Z_ARR_P(lines), lineno, &num_zv);
            }

            increment_function(num);
        }
}

PHP_RINIT_FUNCTION(sample_prof)
        {
                SAMPLE_PROF_G->enabled = 0;
        SAMPLE_PROF_G->entries = NULL;
        SAMPLE_PROF_G->entries_num = 0;

        return SUCCESS;
        }

PHP_RSHUTDOWN_FUNCTION(sample_prof)
        {
                sample_prof_end();
        if (SAMPLE_PROF_G->entries) {
            efree(SAMPLE_PROF_G->entries);
        }

        return SUCCESS;
        }

PHP_MINFO_FUNCTION(sample_prof)
        {
                php_info_print_table_start();
        php_info_print_table_header(2, "sample_prof support", "enabled");
        php_info_print_table_end();
        }

const zend_function_entry sample_prof_functions[] = {
        PHP_FE(sample_prof_start, arginfo_sample_prof_start)
        PHP_FE(sample_prof_end, arginfo_sample_prof_end)
        PHP_FE(sample_prof_get_data, arginfo_sample_prof_get_data)
        PHP_FE_END
};

zend_module_entry sample_prof_module_entry = {
        STANDARD_MODULE_HEADER,
        "sample_prof",
        sample_prof_functions,
        NULL,
        NULL,
        PHP_RINIT(sample_prof),
        PHP_RSHUTDOWN(sample_prof),
        PHP_MINFO(sample_prof),
        PHP_SAMPLE_PROF_VERSION,
        PHP_MODULE_GLOBALS(sample_prof),
        NULL,
        NULL,
        NULL,
        STANDARD_MODULE_PROPERTIES_EX
};
