#pragma once


#include <array>
#include <stdexcept>
#include <Zend/zend_API.h>

#include <Zend/zend_types.h>
#include <Zend/zend_variables.h>

namespace elasticapm::php {

template<std::size_t SIZE = 1>
class AutoZval {
public:
    AutoZval(const AutoZval&) = delete;
    AutoZval& operator=(const AutoZval&) = delete;
    // TODO implement copy constructor or safer - copy_full() and copy_ref() methods
    // TODO implement constructor or separate class for external pointer and don't use member storage then

    AutoZval() {
        for (std::size_t idx = 0; idx < SIZE; ++idx) {
            ZVAL_UNDEF(&value[idx]);
        }
    }

    ~AutoZval() {
        for (std::size_t idx = 0; idx < SIZE; ++idx) {
            zval_ptr_dtor(&value[idx]);
        }
    }

    constexpr zval &operator*() noexcept {
        return value[0];
    }

    constexpr zval *get() noexcept {
        return &value[0];
    }

    constexpr zval &at(std::size_t index) {
        if (index >= SIZE) {
            throw std::out_of_range("AutoZval index greater or equal capacity");
        }
        return value[index];
    }

    constexpr zval *get(std::size_t index) {
        if (index >= SIZE) {
            throw std::out_of_range("AutoZval index greater or equal capacity");
        }
        return value[index];
    }

    zval *data() {
        return &value[0];
    }

    zval &operator[](std::size_t index) {
        if (index >= SIZE) {
            throw std::out_of_range("AutoZval index greater or equal capacity");
        }
        return value[index];
    }

    constexpr std::size_t size() const noexcept {
        return SIZE;
    }

private:
    zval value[SIZE];
};


}