#include "PhpBridge.h"

#include "AutoZval.h"

#include <Zend/zend_API.h>
#include <Zend/zend_alloc.h>
#include <Zend/zend_globals.h>
#include <Zend/zend_types.h>


namespace elasticapm::php {

using namespace std::string_view_literals;

zend_class_entry *PhpBridge::findClassEntry(std::string_view className) const {
    return static_cast<zend_class_entry *>(zend_hash_str_find_ptr(EG(class_table), className.data(), className.length()));
}

zval *PhpBridge::getClassStaticPropertyValue(zend_class_entry *ce, std::string_view propertyName) const {
    if (!ce) {
        return nullptr;
    }

    return zend_read_static_property(ce, propertyName.data(), propertyName.length(), true);
}


zval *PhpBridge::getClassPropertyValue(zend_class_entry *ce, zval *object, std::string_view propertyName) const {
    AutoZval rv;
    //TODO check with allocated on stack

    if (Z_TYPE_P(object) != IS_OBJECT) {
        return nullptr;
    }

#if PHP_VERSION_ID >= 80000
    return zend_read_property(ce, Z_OBJ_P(object), propertyName.data(), propertyName.length(), 1, rv.get());
#else
    return zend_read_property(ce, object, propertyName.data(), propertyName.length(), 1, rv.get());
#endif
}



bool PhpBridge::callMethod(zval *object, std::string_view methodName, zval arguments[], int32_t argCount, zval *returnValue) const {
    AutoZval zMethodName;
	ZVAL_STRINGL(zMethodName.get(), methodName.data(), methodName.length());

#if PHP_VERSION_ID >=80000
    return _call_user_function_impl(object, zMethodName.get(), returnValue, argCount, arguments, nullptr) == SUCCESS;
#else
    return _call_user_function_ex(object, zMethodName.get(), returnValue, argCount, arguments, 0) == SUCCESS;
#endif
}


bool isObjectOfClass(zval *object, std::string_view className) {
    if (!object || Z_TYPE_P(object) != IS_OBJECT) {
        return false;
    }

    if (!Z_OBJCE_P(object)->name) {
        return false;
    }

    return std::string_view{Z_OBJCE_P(object)->name->val, Z_OBJCE_P(object)->name->len} == className; 

}

bool PhpBridge::callInferredSpans(std::chrono::milliseconds duration) const {
    auto phpPartFacadeClass = findClassEntry("elastic\\apm\\impl\\autoinstrument\\phppartfacade"sv);
    if (!phpPartFacadeClass) {
        return false;
    }

    auto objectOfPhpPartFacade = getClassStaticPropertyValue(phpPartFacadeClass, "singletonInstance"sv);
    if (!objectOfPhpPartFacade || Z_TYPE_P(objectOfPhpPartFacade) != IS_OBJECT) {
        return false;
    }

    auto transactionForExtensionRequest =  getClassPropertyValue(phpPartFacadeClass, objectOfPhpPartFacade, "transactionForExtensionRequest"sv);
    if (!isObjectOfClass(transactionForExtensionRequest, "Elastic\\Apm\\Impl\\AutoInstrument\\TransactionForExtensionRequest")) {
        return false;
    }

    zend_class_entry *ceTransactionForExtensionRequest = Z_OBJCE_P(transactionForExtensionRequest);
    if (!ceTransactionForExtensionRequest) {
        return false;
    }

    zval *inferredSpansManager = getClassPropertyValue(ceTransactionForExtensionRequest, transactionForExtensionRequest, "inferredSpansManager"sv);
    if (!isObjectOfClass(inferredSpansManager, "Elastic\\Apm\\Impl\\InferredSpansManager")) {
        return false;
    }

    AutoZval rv;
    AutoZval params;
    ZVAL_LONG(&params[0], duration.count());

    return callMethod(inferredSpansManager, "handleAutomaticCapturing"sv, params.data(), params.size(), rv.get());
}


}
