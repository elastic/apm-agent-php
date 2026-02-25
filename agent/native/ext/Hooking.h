#pragma once

#include <main/php_version.h>
#include <Zend/zend_execute.h>
#include <Zend/zend_types.h>

#include <optional>

namespace elasticapm::php {

class Hooking {
public:

#if PHP_VERSION_ID < 70400
	using zend_error_cb_lineno_t = uint;
#else
	using zend_error_cb_lineno_t = uint32_t;
#endif

#if PHP_VERSION_ID < 80000
    using zend_error_cb_t = void (*)(int type, const char *error_filename, const zend_error_cb_lineno_t error_lineno, const char *format, va_list args); //<8.0
#elif PHP_VERSION_ID < 80100
    using zend_error_cb_t = void (*)(int type, const char *error_filename, const uint32_t error_lineno, zend_string *message); // 8.0
#else
    using zend_error_cb_t = void (*)(int type, zend_string *error_filename, const uint32_t error_lineno, zend_string *message); // 8.1+
#endif

    using zend_execute_internal_t = void (*)(zend_execute_data *execute_data, zval *return_value);
    using zend_interrupt_function_t = void (*)(zend_execute_data *execute_data);


    static Hooking &getInstance() {
        static Hooking instance;
        return instance;        
    }

    void fetchOriginalHooks() {
        original_execute_internal_ = zend_execute_internal;
        original_zend_interrupt_function_ = zend_interrupt_function;
        original_zend_error_cb_ = zend_error_cb;
    }

    void restoreOriginalHooks() {
        zend_execute_internal = original_execute_internal_;
        zend_interrupt_function = original_zend_interrupt_function_;
        zend_error_cb = original_zend_error_cb_;
    }

    void replaceHooks(bool cfgCaptureErrors, bool cfgCaptureErrorsWithPhpPart, bool cfgInferredSpansEnabled);

    zend_execute_internal_t getOriginalExecuteInternal() {
        return original_execute_internal_;
    }

    zend_interrupt_function_t getOriginalZendInterruptFunction() {
        return original_zend_interrupt_function_;
    }

    zend_error_cb_t getOriginalZendErrorCb() {
        return original_zend_error_cb_;
    }


private:
    Hooking(Hooking const &) = delete;
    void operator=(Hooking const &) = delete;
    Hooking() = default;

    zend_execute_internal_t original_execute_internal_ = nullptr;
    zend_interrupt_function_t original_zend_interrupt_function_ = nullptr;
    zend_error_cb_t original_zend_error_cb_ = nullptr;
};


}