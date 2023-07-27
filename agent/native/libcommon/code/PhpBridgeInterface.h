#pragma once

namespace elasticapm::php {


class PhpBridgeInterface {
public:
    virtual ~PhpBridgeInterface() = default;

    virtual bool callInferredSpans() = 0;
};

}
