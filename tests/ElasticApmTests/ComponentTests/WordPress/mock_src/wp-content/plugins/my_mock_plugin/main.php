<?php

use ElasticApmTests\ComponentTests\WordPress\WordPressMockBridge;

/**
 * @package my_mock_plugin
 */
/*
Plugin Name: My mock plugin
Description: This is a mock plugin used for Elastic APM PHP Agent testing.
*/

function my_mock_plugin_filter_callback()
{
    WordPressMockBridge::assertCallbackArgsAsExpected(func_get_args());
    ++WordPressMockBridge::$mockPluginCallbackCallsCount;
    WordPressMockBridge::setCallbackStackTrace(/* ref */ WordPressMockBridge::$mockPluginCallbackFirstCallStackTrace);
    return WordPressMockBridge::$expectedCallbackReturnValue;
}

// Register the same callback twice - it should be the same as registering once
add_filter(WordPressMockBridge::MOCK_PLUGIN_HOOK_NAME, 'my_mock_plugin_filter_callback');
add_filter(WordPressMockBridge::MOCK_PLUGIN_HOOK_NAME, 'my_mock_plugin_filter_callback');
WordPressMockBridge::$removeFilterCalls[] = function () {
    remove_filter(WordPressMockBridge::MOCK_PLUGIN_HOOK_NAME, 'my_mock_plugin_filter_callback');
};
