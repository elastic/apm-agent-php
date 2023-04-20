<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

use ElasticApmTests\ComponentTests\WordPress\WordPressMockBridge;

class MyMockPartOfCore
{
    public static function filterCallback()
    {
        WordPressMockBridge::assertCallbackArgsAsExpected(func_get_args());
        ++WordPressMockBridge::$mockPartOfCoreCallbackCallsCount;
        return WordPressMockBridge::$expectedCallbackReturnValue;
    }

    public static function addFilter()
    {
        add_filter(WordPressMockBridge::MOCK_PART_OF_CORE_HOOK_NAME, [__CLASS__, 'filterCallback']);
    }
}

MyMockPartOfCore::addFilter();
