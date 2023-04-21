<?php /** @noinspection PhpIllegalPsrClassPathInspection */

namespace MyMockThemeNamespace;

use ElasticApmTests\ComponentTests\WordPress\WordPressMockBridge;

class MyMockTheme
{
    public static function filterCallback()
    {
        WordPressMockBridge::assertCallbackArgsAsExpected(func_get_args());
        ++WordPressMockBridge::$mockThemeCallbackCallsCount;
        return WordPressMockBridge::$expectedCallbackReturnValue;
    }
}

add_filter(WordPressMockBridge::MOCK_THEME_HOOK_NAME, MyMockTheme::class . '::filterCallback');
