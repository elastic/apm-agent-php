
#include "PeriodicTaskExecutor.h"

#include <gtest/gtest.h>
#include <gmock/gmock.h>
#include <pthread.h>
#include <sys/wait.h>
#include <unistd.h>

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
    if (globalPeriodicTaskExecutor) globalPeriodicTaskExecutor->prefork();
}

void fh_parent() {
    if (globalPeriodicTaskExecutor) globalPeriodicTaskExecutor->postfork(false);

}

void fh_child() {
    if (globalPeriodicTaskExecutor) globalPeriodicTaskExecutor->postfork(true);

}

TEST(PeriodicTaskExecutorTest, resumeAfterFork) {
    std::atomic_int counter = 0;
    PeriodicTaskExecutor periodicTaskExecutor_{
        {[&counter](PeriodicTaskExecutor::time_point_t tp) {
            counter++;
        }
    }};
    globalPeriodicTaskExecutor = &periodicTaskExecutor_;
    // atfork handlers can't be unregistered: make them no-ops once the executor is gone
    struct GlobalReset {
        ~GlobalReset() { globalPeriodicTaskExecutor = nullptr; }
    } globalReset;

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
    ASSERT_NE(pid, -1);
    std::this_thread::sleep_for(200ms);

    bool counterOk = counter.load() >= 13; // should be 15 in ideal world
    if (pid == 0) {
        // child: never return into gtest, never run atexit handlers/static destructors
        _exit(counterOk ? 0 : 1);
    }

    EXPECT_TRUE(counterOk);

    int status = 0;
    ASSERT_EQ(waitpid(pid, &status, 0), pid);
    EXPECT_TRUE(WIFEXITED(status));
    EXPECT_EQ(WEXITSTATUS(status), 0);
}

TEST(PeriodicTaskExecutorTest, suspendAndResume) {
    std::atomic_int counter = 0;
    PeriodicTaskExecutor executor{{[&counter](PeriodicTaskExecutor::time_point_t) { counter++; }}};
    executor.setInterval(10ms);

    executor.resumePeriodicTasks();
    auto deadline = std::chrono::steady_clock::now() + 5s;
    while (counter.load() < 3 && std::chrono::steady_clock::now() < deadline) {
        std::this_thread::sleep_for(5ms);
    }
    ASSERT_GE(counter.load(), 3);

    executor.suspendPeriodicTasks();
    std::this_thread::sleep_for(50ms); // let an in-flight tick finish
    auto suspendedCount = counter.load();
    std::this_thread::sleep_for(100ms);
    EXPECT_EQ(counter.load(), suspendedCount);

    executor.resumePeriodicTasks();
    deadline = std::chrono::steady_clock::now() + 5s;
    while (counter.load() < suspendedCount + 3 && std::chrono::steady_clock::now() < deadline) {
        std::this_thread::sleep_for(5ms);
    }
    EXPECT_GE(counter.load(), suspendedCount + 3);
}

TEST(PeriodicTaskExecutorTest, preforkDoesNotWaitForWholeInterval) {
    std::atomic_int counter = 0;
    PeriodicTaskExecutor executor{{[&counter](PeriodicTaskExecutor::time_point_t) { counter++; }}};
    executor.setInterval(10s);
    executor.resumePeriodicTasks();
    std::this_thread::sleep_for(50ms); // worker is now sleeping inside the long interval

    auto start = std::chrono::steady_clock::now();
    executor.prefork();
    EXPECT_LT(std::chrono::steady_clock::now() - start, 2s);

    executor.setInterval(10ms);
    executor.postfork(false); // must resume ticking after prefork
    auto deadline = std::chrono::steady_clock::now() + 5s;
    while (counter.load() < 3 && std::chrono::steady_clock::now() < deadline) {
        std::this_thread::sleep_for(5ms);
    }
    EXPECT_GE(counter.load(), 3);
}

TEST(PeriodicTaskExecutorTest, destroyWithoutResume) {
    for (int i = 0; i < 200; ++i) {
        PeriodicTaskExecutor executor{{[](PeriodicTaskExecutor::time_point_t) {}}};
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

