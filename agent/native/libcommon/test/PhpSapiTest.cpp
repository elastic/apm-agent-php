
#include "PhpSapi.h"

#include <gtest/gtest.h>

namespace elasticapm::php {

TEST(PhpSapiTest, testUnknown) {
    PhpSapi sapi("somesapi");
    EXPECT_FALSE(sapi.isSupported());
    EXPECT_EQ(sapi.getType(), PhpSapi::Type::UNKNOWN);
}

TEST(PhpSapiTest, testKnown) {
    PhpSapi sapi("apache2handler");
    EXPECT_TRUE(sapi.isSupported());
    EXPECT_EQ(sapi.getType(), PhpSapi::Type::Apache);
}

TEST(PhpSapiTest, testKnownUnsupported) {
    PhpSapi sapi("phpdbg");
    EXPECT_FALSE(sapi.isSupported());
    EXPECT_EQ(sapi.getType(), PhpSapi::Type::PHPDBG);
}

}
