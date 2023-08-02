
#include "PeriodicTaskExecutor.h"

#include <gtest/gtest.h>
#include <gmock/gmock.h>
#include <pthread.h>

using namespace std::chrono_literals;

namespace elasticapm::php {

PeriodicTaskExecutor *globalPeriodicTaskExecutor = nullptr;

class PeriodicTaskExecutorTest : public ::testing::Test {
public:
	PeriodicTaskExecutorTest() {
        elasticapm::php::globalPeriodicTaskExecutor = &periodicTaskExecutor_;
    }

protected:
	static void SetUpTestCase() {
	}

	static void TearDownTestCase() {
	}

public:
	PeriodicTaskExecutor periodicTaskExecutor_;
};


TEST_F(PeriodicTaskExecutorTest, AutoShutdown) {
    periodicTaskExecutor_.setInterval(20ms);
    periodicTaskExecutor_.addPeriodicTask(
        [](PeriodicTaskExecutor::time_point_t tp) {
        }
    );

    periodicTaskExecutor_.resumeCounting();
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

TEST_F(PeriodicTaskExecutorTest, resumeAfterFork) {
    periodicTaskExecutor_.setInterval(20ms);

    std::atomic_int counter = 0;

    periodicTaskExecutor_.addPeriodicTask(
        [&counter](PeriodicTaskExecutor::time_point_t tp) {
            counter++;
        }
    );

    globalPeriodicTaskExecutor = &periodicTaskExecutor_;
    pthread_atfork(fh_prepare, fh_parent, fh_child);

    periodicTaskExecutor_.resumeCounting();
    std::this_thread::sleep_for(100ms);
    auto counterBeforeFork = counter.load();

    ASSERT_GE(counterBeforeFork, 4); // should be 5 in ideal world

    auto pid = fork();
    std::this_thread::sleep_for(200ms);

    ASSERT_GE(counter.load(), 13); // should be 15 in ideal world
    if (pid == 0) {
        exit(0);
    }
}

}

