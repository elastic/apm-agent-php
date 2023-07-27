
#include "TickGenerator.h"

#include <gtest/gtest.h>
#include <gmock/gmock.h>
#include <pthread.h>

using namespace std::chrono_literals;

namespace elasticapm::php {

TickGenerator *globalGenerator = nullptr;

class TickGeneratorTest : public ::testing::Test {
public:
	TickGeneratorTest() {
        elasticapm::php::globalGenerator = &tickGenerator_;
    }

protected:
	static void SetUpTestCase() {
	}

	static void TearDownTestCase() {
	}

public:
	TickGenerator tickGenerator_;
};


TEST_F(TickGeneratorTest, AutoShutdown) {
    tickGenerator_.setInterval(20ms);
    tickGenerator_.addPeriodicTask(
        [](TickGenerator::time_point_t tp) {
        }
    );

    tickGenerator_.resumeCounting();
    std::this_thread::sleep_for(100ms);
}


void fh_prepare() {
    globalGenerator->prefork();
}

void fh_parent() {
    globalGenerator->postfork(false);

}

void fh_child() {
    globalGenerator->postfork(true);

}

TEST_F(TickGeneratorTest, resumeAfterFork) {
    tickGenerator_.setInterval(20ms);

    std::atomic_int counter = 0;

    tickGenerator_.addPeriodicTask(
        [&counter](TickGenerator::time_point_t tp) {
            counter++;
        }
    );

    globalGenerator = &tickGenerator_;
    pthread_atfork(fh_prepare, fh_parent, fh_child);

    tickGenerator_.resumeCounting();
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

