#pragma once

#include "ForkableInterface.h"

#include <atomic>
#include <condition_variable>
#include <functional>
#include <stop_token>
#include <thread>
#include <vector>

namespace elasticapm::php {


class PeriodicTaskExecutor : public ForkableInterface {
private:
    auto getThreadWorkerFunction() {
        return [this]() { work(); };
    }

public:
	using clock_t = std::chrono::steady_clock;
	using time_point_t = std::chrono::time_point<clock_t, std::chrono::milliseconds>;

    using task_t = std::function<void(time_point_t)>;
    using worker_init_t = std::function<void()>;

    PeriodicTaskExecutor(std::vector<task_t> periodicTasks, worker_init_t workerInit = {}) : periodicTasks_(periodicTasks), workerInit_(std::move(workerInit)),  thread_(getThreadWorkerFunction()) {
    }

    ~PeriodicTaskExecutor() {
        shutdown();

        if (thread_.joinable()) {
            thread_.join();
        }
    }

    void work() {
        if (workerInit_) {
            workerInit_();
        }

        std::unique_lock<std::mutex> lock(mutex_);
        while(working_) {
            pauseCondition_.wait(lock, [this]() -> bool {
                return resumed_ || !working_;
            });

            if (!working_) {
                break;
            }

            lock.unlock();
            std::this_thread::sleep_for(sleepInterval_);
            for (auto const &task : periodicTasks_) {
                task(std::chrono::time_point_cast<std::chrono::milliseconds>(clock_t::now()));
            }
            lock.lock();
        }
    }

    void prefork() final {
        shutdown();
        thread_.join();
    }

    void postfork([[maybe_unused]] bool child) final {
        working_ = true;
        thread_ = std::thread(getThreadWorkerFunction());
        pauseCondition_.notify_all();
    }

    void resumePeriodicTasks() {
        {
        std::lock_guard<std::mutex> lock(mutex_);
        resumed_ = true;
        }
        pauseCondition_.notify_all();
    }
    void suspendPeriodicTasks() {
        {
       	std::lock_guard<std::mutex> lock(mutex_);
        resumed_ = false;
        }
        pauseCondition_.notify_all();
    }

    void setInterval(std::chrono::milliseconds interval) {
        sleepInterval_ = interval;
    }

private:
   PeriodicTaskExecutor(const PeriodicTaskExecutor&) = delete;
   PeriodicTaskExecutor& operator=(const PeriodicTaskExecutor&) = delete;

   void shutdown() {
        {
        std::lock_guard<std::mutex> lock(mutex_);
        working_ = false;
        }
        pauseCondition_.notify_all();
   }

private:

    std::chrono::milliseconds sleepInterval_ = std::chrono::milliseconds(20);
    std::vector<task_t> periodicTasks_;
    worker_init_t workerInit_;
    std::thread thread_;
    std::mutex mutex_;
    std::condition_variable pauseCondition_;
    bool working_ = true;
    bool resumed_ = false;
};


}
