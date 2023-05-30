<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

use ElasticApmTests\ComponentTests\WordPress\WordPressMockBridge;
use ElasticApmTests\Util\TestCaseBase;
use PHPUnit\Framework\Assert;

class MyMockPartOfCore
{
    private const IMPLICIT_METHOD_NAME = 'filterCallback';

    public function __call($methodName, $args)
    {
        Assert::assertSame(self::IMPLICIT_METHOD_NAME, $methodName);

        TestCaseBase::assertEqualLists([$methodName, $args], func_get_args());

        WordPressMockBridge::assertCallbackArgsAsExpected($args);
        ++WordPressMockBridge::$mockPartOfCoreCallbackCallsCount;
        WordPressMockBridge::setCallbackStackTrace(/* ref */ WordPressMockBridge::$mockPartOfCoreCallbackFirstCallStackTrace);
        return WordPressMockBridge::$expectedCallbackReturnValue;
    }

    public static function registerCallback(): void
    {
        $callback = [new self(), self::IMPLICIT_METHOD_NAME];
        add_filter(WordPressMockBridge::MOCK_PART_OF_CORE_HOOK_NAME, $callback);
        WordPressMockBridge::$removeFilterCalls[] = function () use ($callback) {
            remove_filter(WordPressMockBridge::MOCK_PART_OF_CORE_HOOK_NAME, $callback);
        };
    }
}

MyMockPartOfCore::registerCallback();
