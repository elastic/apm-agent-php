#include "InferredSpans.h"

#include <gtest/gtest.h>
#include <gmock/gmock.h>

namespace elasticapm::php {

using namespace std::chrono_literals;

class InterruptFuncMock {
public:
    MOCK_METHOD(void, interruptFunction, ());
};

class AttachInferredSpansFuncMock {
public:
    MOCK_METHOD(void, attachInferredSpansOnPhp, (InferredSpans::time_point_t interruptRequest, InferredSpans::time_point_t now));
};


class InferredSpansTest : public ::testing::Test {
public:
    InferredSpansTest() {
    }

protected:
	static void SetUpTestCase() {
	}

	static void TearDownTestCase() {
	}

public:
    InterruptFuncMock interruptFuncMock_;
    AttachInferredSpansFuncMock attachInferredSpansFuncMock_;
    InferredSpans inferredSpans_{
        [this]() { interruptFuncMock_.interruptFunction(); },
        [this](InferredSpans::time_point_t interruptRequest, InferredSpans::time_point_t now) { attachInferredSpansFuncMock_.attachInferredSpansOnPhp(interruptRequest, now); }
        };
};


TEST_F(InferredSpansTest, TryInterrupt) {
    inferredSpans_.setInterval(1ms);

    EXPECT_CALL(interruptFuncMock_, interruptFunction()).Times(::testing::Exactly(1));

    inferredSpans_.tryRequestInterrupt(std::chrono::time_point_cast<std::chrono::milliseconds>(InferredSpans::clock_t::now()));

    EXPECT_CALL(attachInferredSpansFuncMock_, attachInferredSpansOnPhp(::testing::_, ::testing::_)).Times(::testing::Exactly(1));

    inferredSpans_.attachBacktraceIfInterrupted();

}

TEST_F(InferredSpansTest, TryInterruptOnlyOnce) {
    inferredSpans_.setInterval(1ms);

    EXPECT_CALL(interruptFuncMock_, interruptFunction()).Times(::testing::Exactly(1));

    inferredSpans_.tryRequestInterrupt(std::chrono::time_point_cast<std::chrono::milliseconds>(InferredSpans::clock_t::now()));
    inferredSpans_.tryRequestInterrupt(std::chrono::time_point_cast<std::chrono::milliseconds>(InferredSpans::clock_t::now()));
    inferredSpans_.tryRequestInterrupt(std::chrono::time_point_cast<std::chrono::milliseconds>(InferredSpans::clock_t::now()));
}

TEST_F(InferredSpansTest, DontInterruptBeforeInterval) {
    inferredSpans_.setInterval(10min);

    ::testing::InSequence s;
    EXPECT_CALL(interruptFuncMock_, interruptFunction()).Times(::testing::Exactly(1));
    inferredSpans_.tryRequestInterrupt(std::chrono::time_point_cast<std::chrono::milliseconds>(InferredSpans::clock_t::now()));

    EXPECT_CALL(attachInferredSpansFuncMock_, attachInferredSpansOnPhp(::testing::_, ::testing::_)).Times(::testing::Exactly(1));
    inferredSpans_.attachBacktraceIfInterrupted();

    EXPECT_CALL(interruptFuncMock_, interruptFunction()).Times(::testing::Exactly(0));
    inferredSpans_.tryRequestInterrupt(std::chrono::time_point_cast<std::chrono::milliseconds>(InferredSpans::clock_t::now()));
}

TEST_F(InferredSpansTest, InterruptAfterInterval) {
    inferredSpans_.setInterval(5ms);

    EXPECT_CALL(interruptFuncMock_, interruptFunction()).Times(::testing::Exactly(1));
    inferredSpans_.tryRequestInterrupt(std::chrono::time_point_cast<std::chrono::milliseconds>(InferredSpans::clock_t::now()));

    EXPECT_CALL(attachInferredSpansFuncMock_, attachInferredSpansOnPhp(::testing::_, ::testing::_)).Times(::testing::Exactly(1));
    inferredSpans_.attachBacktraceIfInterrupted();

    std::this_thread::sleep_for(10ms);

    EXPECT_CALL(interruptFuncMock_, interruptFunction()).Times(::testing::Exactly(1));
    inferredSpans_.tryRequestInterrupt(std::chrono::time_point_cast<std::chrono::milliseconds>(InferredSpans::clock_t::now()));
}

TEST_F(InferredSpansTest, DontInterruptAfterIntervalIfSpansNotAttachedYet) {
    inferredSpans_.setInterval(5ms);
 
    EXPECT_CALL(interruptFuncMock_, interruptFunction()).Times(::testing::Exactly(1));
    inferredSpans_.tryRequestInterrupt(std::chrono::time_point_cast<std::chrono::milliseconds>(InferredSpans::clock_t::now()));

    std::this_thread::sleep_for(10ms);

    EXPECT_CALL(interruptFuncMock_, interruptFunction()).Times(::testing::Exactly(0));
    inferredSpans_.tryRequestInterrupt(std::chrono::time_point_cast<std::chrono::milliseconds>(InferredSpans::clock_t::now()));
}


}

