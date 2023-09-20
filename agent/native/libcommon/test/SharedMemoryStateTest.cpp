
#include "SharedMemoryState.h"

#include <atomic>
#include <thread>
#include <gtest/gtest.h>

namespace elasticapm::php {

class SharedMemoryStateTest : public ::testing::Test {
public:
    SharedMemoryState state_;
};


TEST_F(SharedMemoryStateTest, shouldExecuteOneTimeTaskAmongWorkers) {
    ASSERT_TRUE(state_.shouldExecuteOneTimeTaskAmongWorkers());
    ASSERT_FALSE(state_.shouldExecuteOneTimeTaskAmongWorkers());
    ASSERT_FALSE(state_.shouldExecuteOneTimeTaskAmongWorkers());
    ASSERT_FALSE(state_.shouldExecuteOneTimeTaskAmongWorkers());
}

TEST_F(SharedMemoryStateTest, shouldExecuteOneTimeTaskAmongWorkersFromThreads) {
    std::atomic_int32_t counter = 0;

    auto test = [&]() {
        if (state_.shouldExecuteOneTimeTaskAmongWorkers()) {
            counter++;
        }
    };

    std::thread  t1{test}, t2{test}, t3{test}, t4{test}, t5{test}, t6{test}, t7{test};

    t1.join();
    t2.join();
    t3.join();
    t4.join();
    t5.join();
    t6.join();
    t7.join();

    EXPECT_EQ(counter.load(), 1);
}


}
