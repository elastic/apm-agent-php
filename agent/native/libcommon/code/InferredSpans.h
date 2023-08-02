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

    void attachBacktraceIfInterrupted() {
        if (phpSideBacktracePending_.load()) { // avoid triggers from agent side with low interval
            return;
        }

        std::unique_lock lock(mutex_);
        time_point_t requestInterruptTime = lastInterruptRequestTick_;

        if (checkAndResetInterruptFlag()) {
            lock.unlock();
            phpSideBacktracePending_ = true;
            attachInferredSpansOnPhp_(requestInterruptTime, std::chrono::time_point_cast<std::chrono::milliseconds>(clock_t::now()));
            phpSideBacktracePending_ = false;
        }
    }

	void tryRequestInterrupt(time_point_t now) {
        std::unique_lock lock(mutex_);
        if (now > lastInterruptRequestTick_ + samplingInterval_) {
            if (interruptedRequested_.load()) {
                return; // it was requested to interrupt in previous interval
            } 
            lastInterruptRequestTick_ = now;

            interruptedRequested_ = true;
            lock.unlock();
            interrupt_(); // set interrupt for user space functions
        }

    }


    void setInterval(std::chrono::milliseconds interval) {
        samplingInterval_ = interval;
    }

private:
    bool checkAndResetInterruptFlag() {
        bool interrupted = true;
        return interruptedRequested_.compare_exchange_strong(interrupted, false, std::memory_order_release, std::memory_order_relaxed);
    }

    std::atomic_bool interruptedRequested_;
    std::chrono::milliseconds samplingInterval_ = std::chrono::milliseconds(20);
    time_point_t lastInterruptRequestTick_{};
    std::mutex mutex_;
    interruptFunc_t interrupt_;
    attachInferredSpansOnPhp_t attachInferredSpansOnPhp_;
    std::atomic_bool phpSideBacktracePending_;
};


}