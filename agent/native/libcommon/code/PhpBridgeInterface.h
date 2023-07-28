#pragma once

#include <chrono>

namespace elasticapm::php {


class PhpBridgeInterface {
public:
    virtual ~PhpBridgeInterface() = default;

    virtual bool callInferredSpans(std::chrono::milliseconds duration) = 0;
};

}
