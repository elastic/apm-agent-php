
#include "CommonUtils.h"

#include <gtest/gtest.h>
#include <gmock/gmock.h>
#include <pthread.h>

#include <chrono>
#include <string_view>


using namespace std::chrono_literals;

namespace elasticapm::utils {

TEST(CommunUtilsTest, convertDurationWithUnit) {
    EXPECT_THROW(convertDurationWithUnit("1  s  s"), std::invalid_argument);
    EXPECT_THROW(convertDurationWithUnit("1xd"), std::invalid_argument);
    EXPECT_THROW(convertDurationWithUnit("1h"), std::invalid_argument);

    ASSERT_EQ(convertDurationWithUnit("1ms"), 1ms);
    ASSERT_EQ(convertDurationWithUnit("1.6ms"), 1ms);
    ASSERT_EQ(convertDurationWithUnit(" 10 ms"), 10ms);
    ASSERT_EQ(convertDurationWithUnit("   \t 10000 m s\t\n "), 10000ms);

    ASSERT_EQ(convertDurationWithUnit("0s"), 0ms);
    ASSERT_EQ(convertDurationWithUnit("1s"), 1000ms);
    ASSERT_EQ(convertDurationWithUnit("0.1s"), 100ms);
    ASSERT_EQ(convertDurationWithUnit("0.01s"), 10ms);
    ASSERT_EQ(convertDurationWithUnit("0.001s"), 1ms);
    ASSERT_EQ(convertDurationWithUnit("0.0001s"), 0ms);

    ASSERT_EQ(convertDurationWithUnit("1m"), 60000ms);
    ASSERT_EQ(convertDurationWithUnit("10m"), 600000ms);
    ASSERT_EQ(convertDurationWithUnit("10.5m"), 630000ms);

    ASSERT_EQ(convertDurationWithUnit("  1234  \t"), 1234ms);
}


}

