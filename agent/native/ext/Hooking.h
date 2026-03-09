#pragma once

#include <main/php_version.h>
#include <Zend/zend_execute.h>
#include <Zend/zend_types.h>

#include <optional>

namespace elasticapm::php {

class Hooking {
public:

    using zend_execute_internal_t = void (*)(zend_execute_data *execute_data, zval *return_value);
    using zend_interrupt_function_t = void (*)(zend_execute_data *execute_data);


    static Hooking &getInstance() {
        static Hooking instance;
        return instance;        
    }

    void fetchOriginalHooks() {
        original_execute_internal_ = zend_execute_internal;
        original_zend_interrupt_function_ = zend_interrupt_function;
    }

    void restoreOriginalHooks() {
        zend_execute_internal = original_execute_internal_;
        zend_interrupt_function = original_zend_interrupt_function_;
    }

    void replaceHooks(bool cfgInferredSpansEnabled);

    zend_execute_internal_t getOriginalExecuteInternal() {
        return original_execute_internal_;
    }

    zend_interrupt_function_t getOriginalZendInterruptFunction() {
        return original_zend_interrupt_function_;
    }

private:
    Hooking(Hooking const &) = delete;
    void operator=(Hooking const &) = delete;
    Hooking() = default;

    zend_execute_internal_t original_execute_internal_ = nullptr;
    zend_interrupt_function_t original_zend_interrupt_function_ = nullptr;
};


}