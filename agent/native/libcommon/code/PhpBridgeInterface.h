#pragma once

#include <chrono>
#include <string>
#include <vector>

namespace elasticapm::php {


class PhpBridgeInterface {
public:

    struct phpExtensionInfo_t {
        std::string name;
        std::string version;
    };

    virtual ~PhpBridgeInterface() = default;

    virtual bool callInferredSpans(std::chrono::milliseconds duration) const = 0;
    virtual std::vector<phpExtensionInfo_t> getExtensionList() const = 0;
    virtual std::string getPhpInfo() const = 0;

    virtual std::string_view getPhpSapiName() const = 0;
    virtual bool isExtensionLoaded(std::string_view extensionName) const = 0;
};

}
