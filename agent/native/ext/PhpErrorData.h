#pragma once

#include <Zend/zend_types.h>
#include <string>
#include <string_view>

namespace elasticapm::php {

class PhpErrorData {
public:
    PhpErrorData(int type, std::string_view fileName, uint32_t lineNumber, std::string_view message);
    ~PhpErrorData();

    int getType() const;
    std::string_view getFileName() const;
    int getLineNumber() const;
    std::string_view getMessage() const;
    zval *getStackTrace();

private:
    int type_ = -1;
    std::string fileName_;
    uint32_t lineNumber_ = 0;
    std::string message_;
    zval stackTrace_;
};
    
}