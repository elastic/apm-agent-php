
#include "Hooking.h"

#include <string_view>
#include <Zend/zend_API.h>
#include "php_elastic_apm.h"

#include "PhpBridge.h"

namespace elasticapm::php {

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

void Hooking::replaceHooks() {
        zend_execute_internal = elastic_execute_internal;
        zend_interrupt_function = elastic_interrupt_function;
}

}
