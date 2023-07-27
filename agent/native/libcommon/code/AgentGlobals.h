#pragma once

#include "InferredSpans.h"
#include "TickGenerator.h"
#include "PhpBridgeInterface.h"
#include <memory>

namespace elasticapm::php {

class AgentGlobals {
public:
    AgentGlobals(std::unique_ptr<PhpBridgeInterface> bridge, std::unique_ptr<TickGenerator> tickGenerator, std::shared_ptr<InferredSpans> inferredSpans) :
        bridgeStorage_(std::move(bridge)),
        bridge_(*bridgeStorage_),
        tickGenerator_(std::move(tickGenerator)),
        inferredSpans_(std::move(inferredSpans)) {
    }

    std::unique_ptr<PhpBridgeInterface> bridgeStorage_;
    PhpBridgeInterface &bridge_;
    
    std::unique_ptr<TickGenerator> tickGenerator_;
    std::shared_ptr<InferredSpans> inferredSpans_;
};

    
}