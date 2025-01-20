
#include "PeriodicTaskExecutor.h"

#include <gtest/gtest.h>
#include <gmock/gmock.h>
#include <pthread.h>

using namespace std::chrono_literals;

namespace elasticapm::php {

PeriodicTaskExecutor *globalPeriodicTaskExecutor = nullptr;

TEST(PeriodicTaskExecutorTest, AutoShutdown) {
    PeriodicTaskExecutor periodicTaskExecutor_{{[](PeriodicTaskExecutor::time_point_t tp) {
        }}};

    periodicTaskExecutor_.setInterval(20ms);

    periodicTaskExecutor_.resumePeriodicTasks();
    std::this_thread::sleep_for(100ms);
}


void fh_prepare() {
    globalPeriodicTaskExecutor->prefork();
}

void fh_parent() {
    globalPeriodicTaskExecutor->postfork(false);

}

void fh_child() {
    globalPeriodicTaskExecutor->postfork(true);

}

TEST(PeriodicTaskExecutorTest, resumeAfterFork) {
    std::atomic_int counter = 0;
    PeriodicTaskExecutor periodicTaskExecutor_{
        {[&counter](PeriodicTaskExecutor::time_point_t tp) {
            counter++;
        }
    }};
    globalPeriodicTaskExecutor = &periodicTaskExecutor_;

    periodicTaskExecutor_.setInterval(20ms);


    static bool pthread_atfork_called = false;

    if (!pthread_atfork_called) {
        pthread_atfork(fh_prepare, fh_parent, fh_child);
        pthread_atfork_called = true;
    }

    periodicTaskExecutor_.resumePeriodicTasks();
    std::this_thread::sleep_for(100ms);
    auto counterBeforeFork = counter.load();

    ASSERT_GE(counterBeforeFork, 4); // should be 5 in ideal world

    auto pid = fork();
    std::this_thread::sleep_for(200ms);

    ASSERT_GE(counter.load(), 13); // should be 15 in ideal world
    if (pid == 0) {
        exit(testing::Test::HasFailure());
    }
}

TEST(PeriodicTaskExecutorTest, simpleRun) {
    std::atomic_int counter = 0;

    {
        PeriodicTaskExecutor periodicTaskExecutor_{
            {[&counter](PeriodicTaskExecutor::time_point_t tp) {
                counter++;
            }
        }};

        periodicTaskExecutor_.setInterval(10ms);
        periodicTaskExecutor_.resumePeriodicTasks();
        std::this_thread::sleep_for(59ms);
    }

    ASSERT_GE(counter.load(), 5); // should be 5 in ideal world
}



}

