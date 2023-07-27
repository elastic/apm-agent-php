#pragma once

#include <Zend/zend_API.h>

#include <Zend/zend_types.h>
#include <Zend/zend_variables.h>

namespace elasticapm::php {

class AutoZval {
public:
    AutoZval(const AutoZval&) = delete;
    AutoZval& operator=(const AutoZval&) = delete;
    // TODO implement copy constructor or safer - copy_full() and copy_ref() methods
    // TODO implement constructor or separate class for external pointer and don't use member storage then

    AutoZval() {
        ZVAL_UNDEF(&value);
    }

    ~AutoZval() {
        zval_ptr_dtor(&value);
    }

    zval &operator*() {
        return value;
    }

    zval *get() {
        return &value;
    }

private:
    zval value;
};


}