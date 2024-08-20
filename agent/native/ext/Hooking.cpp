
#include "Hooking.h"

#include <Zend/zend_API.h>

#include "php_elastic_apm.h"

#include "PhpBridge.h"
#include "PhpErrorData.h"

#include <memory>
#include <string_view>

namespace elasticapm::php {

#if PHP_VERSION_ID < 80000
void elastic_apm_error_cb(int type, const char *error_filename, const Hooking::zend_error_cb_lineno_t error_lineno, const char *format, va_list args) { //<8.0
#elif PHP_VERSION_ID < 80100
void elastic_apm_error_cb(int type, const char *error_filename, const uint32_t error_lineno, zend_string *message) { // 8.0
#else
void elastic_apm_error_cb(int type, zend_string *error_filename, const uint32_t error_lineno, zend_string *message) { // 8.1+
#endif
    using namespace std::string_view_literals;

    if (ELASTICAPM_G(captureErrors)) {
#if PHP_VERSION_ID < 80000
        char * message = nullptr;
        va_list messageArgsCopy;
        va_copy(messageArgsCopy, args);
        vspprintf(/* out */ &message, 0, format, messageArgsCopy); // vspprintf allocates memory for the resulted string buffer and it needs to be freed with efree()
        va_end(messageArgsCopy);

        ELASTICAPM_G(lastErrorData) = std::make_unique<elasticapm::php::PhpErrorData>(type, error_filename ? error_filename : ""sv, error_lineno, message ? message : ""sv);

        if (message) {
            efree(message);
        }
#elif PHP_VERSION_ID < 80100
        ELASTICAPM_G(lastErrorData) = std::make_unique<elasticapm::php::PhpErrorData>(type, error_filename ? error_filename : ""sv, error_lineno, message ? std::string_view{ZSTR_VAL(message), ZSTR_LEN(message)} : ""sv);
#else
        ELASTICAPM_G(lastErrorData) = nullptr;
        ELASTICAPM_G(lastErrorData) = std::make_unique<elasticapm::php::PhpErrorData>(type, error_filename ? std::string_view{ZSTR_VAL(error_filename), ZSTR_LEN(error_filename)} : ""sv, error_lineno, message ? std::string_view{ZSTR_VAL(message), ZSTR_LEN(message)} : ""sv);
#endif
    }

    auto original = Hooking::getInstance().getOriginalZendErrorCb();
    if (original == elastic_apm_error_cb) {
        ELASTIC_APM_LOG_DIRECT_CRITICAL("originalZendErrorCallback == elasticApmZendErrorCallback dead loop detected");
        return;
    }

    if (original) {
#if PHP_VERSION_ID < 80000
        original(type, error_filename, error_lineno, format, args);
#else
        original(type, error_filename, error_lineno, message);
#endif
    }
}

static void elastic_execute_internal(INTERNAL_FUNCTION_PARAMETERS) {

    zend_try {
        if (Hooking::getInstance().getOriginalExecuteInternal()) {
            Hooking::getInstance().getOriginalExecuteInternal()(INTERNAL_FUNCTION_PARAM_PASSTHRU);
        } else {
            execute_internal(INTERNAL_FUNCTION_PARAM_PASSTHRU);
        }
    } zend_catch {
        ELASTIC_APM_LOG_DIRECT_DEBUG("%s: original call error; parent PID: %d", __FUNCTION__, (int)getParentProcessId());
    } zend_end_try();

    ELASTICAPM_G(globals)->inferredSpans_->attachBacktraceIfInterrupted();
}


static void elastic_interrupt_function(zend_execute_data *execute_data) {
    ELASTIC_APM_LOG_DIRECT_DEBUG( "%s: interrupt; parent PID: %d", __FUNCTION__, (int)getParentProcessId() );

    ELASTICAPM_G(globals)->inferredSpans_->attachBacktraceIfInterrupted();

    zend_try {
        if (Hooking::getInstance().getOriginalZendInterruptFunction()) {
            Hooking::getInstance().getOriginalZendInterruptFunction()(execute_data);
        }
    } zend_catch {
        ELASTIC_APM_LOG_DIRECT_DEBUG("%s: original call error; parent PID: %d", __FUNCTION__, (int)getParentProcessId());
    } zend_end_try();
}

void Hooking::replaceHooks(bool cfgCaptureErrors, bool cfgInferredSpansEnabled) {
    if (cfgInferredSpansEnabled) {
        zend_execute_internal = elastic_execute_internal;
        zend_interrupt_function = elastic_interrupt_function;
        ELASTIC_APM_LOG_DEBUG( "Replaced zend_execute_internal and zend_interrupt_function hooks" );
    } else {
        ELASTIC_APM_LOG_DEBUG( "NOT replacing zend_execute_internal and zend_interrupt_function hooks because profiling_inferred_spans_enabled configuration option is set to false" );
    }

    if (cfgCaptureErrors) {
        zend_error_cb = elastic_apm_error_cb;
        ELASTIC_APM_LOG_DEBUG( "Replaced zend_error_cb hook" );
    } else {
        ELASTIC_APM_LOG_DEBUG( "NOT replacing zend_error_cb hook because capture_errors configuration option is set to false" );
    }
}

}
