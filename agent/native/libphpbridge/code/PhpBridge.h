#pragma once

#include <main/php_version.h>
#include <Zend/zend_types.h>
#include <string_view>

#include "PhpBridgeInterface.h"

namespace elasticapm::php {

class PhpBridge : public PhpBridgeInterface {
public:

    bool callInferredSpans(std::chrono::milliseconds duration) const final;

    std::vector<phpExtensionInfo_t> getExtensionList() const final;
    std::string getPhpInfo() const final;

    std::string_view getPhpSapiName() const final;
    bool isExtensionLoaded(std::string_view extensionName) const final;

protected:
    zend_class_entry *findClassEntry(std::string_view className) const;
    zval *getClassStaticPropertyValue(zend_class_entry *ce, std::string_view propertyName) const;
    zval *getClassPropertyValue(zend_class_entry *ce, zval *object, std::string_view propertyName) const;
    bool callMethod(zval *object, std::string_view methodName, zval arguments[], int32_t argCount, zval *returnValue) const;
};


}