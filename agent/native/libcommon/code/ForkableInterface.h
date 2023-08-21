#pragma once

namespace elasticapm::php {

class ForkableInterface {
public:
    virtual ~ForkableInterface() {
    }

    virtual void prefork() = 0;
    virtual void postfork([[maybe_unused]] bool child) = 0;
};

}