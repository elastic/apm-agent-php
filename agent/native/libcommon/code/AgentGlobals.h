#pragma once

#include "InferredSpans.h"
#include "PeriodicTaskExecutor.h"
#include "PhpBridgeInterface.h"
#include <memory>

namespace elasticapm::php {

class AgentGlobals {
public:
    AgentGlobals(std::shared_ptr<PhpBridgeInterface> bridge, std::unique_ptr<PeriodicTaskExecutor> periodicTaskExecutor, std::shared_ptr<InferredSpans> inferredSpans) :
        bridge_(std::move(bridge)),
        periodicTaskExecutor_(std::move(periodicTaskExecutor)),
        inferredSpans_(std::move(inferredSpans)) {
    }

    std::shared_ptr<PhpBridgeInterface> bridge_;
    std::unique_ptr<PeriodicTaskExecutor> periodicTaskExecutor_;
    std::shared_ptr<InferredSpans> inferredSpans_;
};

    
}