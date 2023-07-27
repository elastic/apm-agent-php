#pragma once

#include <atomic>
#include <chrono>
#include <functional>
#include <mutex>

namespace elasticapm::php {


class InferredSpans {
public:
    
    using clock_t = std::chrono::steady_clock;
	using time_point_t = std::chrono::time_point<clock_t, std::chrono::milliseconds>;
    using interruptFunc_t = std::function<void()>; 
    using attachInferredSpansOnPhp_t = std::function<void(time_point_t interruptRequest, time_point_t now)>;

    InferredSpans(interruptFunc_t interrupt, attachInferredSpansOnPhp_t attachInferredSpansOnPhp) : interrupt_(interrupt), attachInferredSpansOnPhp_(attachInferredSpansOnPhp) {
    }

    void getBacktrace() {
        time_point_t requestInterruptTime;
        {
        std::lock_guard<std::mutex> lock(mutex_);
        requestInterruptTime = lastInterruptRequestTick_;
        }

        attachInferredSpansOnPhp_(requestInterruptTime, std::chrono::time_point_cast<std::chrono::milliseconds>(clock_t::now()));
    }

	void tryRequestInterrupt(time_point_t now) {
        std::unique_lock lock(mutex_);
        if (now > lastInterruptRequestTick_ + samplingInterval_) {
            if (interruptedRequested_.load()) {
                return; // it was requested to interrupt in previous interval
            } 
            lastInterruptRequestTick_ = now;

            lock.unlock();
            requestInterrupt();
        }

    }

    bool wasInterruptRequestedAndReset() {
    	bool interrupted = true;
	    return interruptedRequested_.compare_exchange_strong(interrupted, false, std::memory_order_release, std::memory_order_relaxed);
    }

    void setInterval(std::chrono::milliseconds interval) {
        samplingInterval_ = interval;
    }

private:
    void requestInterrupt() {
        interruptedRequested_ = true;
        interrupt_();
    }


    std::atomic_bool interruptedRequested_;
    std::chrono::milliseconds samplingInterval_ = std::chrono::milliseconds(100);
    time_point_t lastInterruptRequestTick_{};
    std::mutex mutex_;
    interruptFunc_t interrupt_;
    attachInferredSpansOnPhp_t attachInferredSpansOnPhp_;
};


}