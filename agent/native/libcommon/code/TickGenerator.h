#pragma once

#include "ForkableInterface.h"

#include <atomic>
#include <condition_variable>
#include <functional>
#include <list>
#include <stop_token>
#include <thread>

namespace elasticapm::php {


class TickGenerator : public ForkableInterface {
private:
    auto getThread() {
        return [this](std::stop_token stoken) { work(stoken); };
    }

public:
	using clock_t = std::chrono::steady_clock;
	using time_point_t = std::chrono::time_point<clock_t, std::chrono::milliseconds>;

    using task_t = std::function<void(time_point_t)>;
    using worker_init_t = std::function<void()>;

    TickGenerator(worker_init_t workerInit = {}) : workerInit_(std::move(workerInit)),  thread_(getThread()) {
    }

    ~TickGenerator() {
        shutdown();
        thread_.join();
    }

    void work(std::stop_token stoken) {

        if (workerInit_) {
            workerInit_();
        }

        while(!stoken.stop_requested()) {
            {
                std::unique_lock<std::mutex> lock(mutex_);

                if (!counting_.load()) {
                    pauseCondition_.wait(lock, [this, &stoken]() {
                        if (stoken.stop_requested()) {
                            return true;
                        }
                        return static_cast<bool>(counting_);
                    });
                }
            }

            if (stoken.stop_requested()) {
                break;
            }

            std::this_thread::sleep_for(sleepInterval_);
            {
                std::unique_lock<std::mutex> lock(mutex_);
                for (auto const &task : periodicTasks_) {

                    lock.unlock();
                    task(std::chrono::time_point_cast<std::chrono::milliseconds>(clock_t::now()));
                    lock.lock();
                }
            }
        }
    }

    void prefork() final {
        shutdown();
        thread_.join();
    }

    void postfork([[maybe_unused]] bool child) final {
        thread_ = std::move(std::jthread(getThread()));
        pauseCondition_.notify_all();
    }

    void resumeCounting() {
        {
        std::lock_guard<std::mutex> lock(mutex_);
        counting_ = true;
        }
        pauseCondition_.notify_all();
    }
    void pauseCounting() {
        {
       	std::lock_guard<std::mutex> lock(mutex_);
        counting_ = false;
        }
        pauseCondition_.notify_all();
    }

    void addPeriodicTask(task_t task) {
        std::lock_guard<std::mutex> lock(mutex_);
        periodicTasks_.emplace_back(std::move(task));
    }

    void setInterval(std::chrono::milliseconds interval) {
        sleepInterval_ = interval;
    }

private:
   TickGenerator(const TickGenerator&) = delete;
   TickGenerator& operator=(const TickGenerator&) = delete;

   void shutdown() {
        thread_.request_stop();
        pauseCondition_.notify_one();
   }

private:

    worker_init_t workerInit_;
    std::jthread thread_;
    std::atomic_bool counting_ = false;
    std::chrono::milliseconds sleepInterval_ = std::chrono::milliseconds(20);
    std::mutex mutex_;
	std::condition_variable pauseCondition_;
    std::list<task_t> periodicTasks_;
};


}
