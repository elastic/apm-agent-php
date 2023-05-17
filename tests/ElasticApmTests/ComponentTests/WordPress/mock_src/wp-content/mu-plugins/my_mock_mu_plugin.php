<?php

use ElasticApmTests\ComponentTests\WordPress\WordPressMockBridge;
use PHPUnit\Framework\Assert;

/**
 * @package my_mock_mu_plugin
 */
/*
Plugin Name: My mock must-use plugin
Description: This is a mock plugin used for Elastic APM PHP Agent testing.
*/

$my_mock_mu_plugin_callback = function () {
    WordPressMockBridge::assertCallbackArgsAsExpected(func_get_args());
    ++WordPressMockBridge::$mockMuPluginCallbackCallsCount;
    return WordPressMockBridge::$expectedCallbackReturnValue;
};

// Add, remove and re-add back to test that remove works with instrumented _wp_filter_build_unique_id
add_filter(WordPressMockBridge::MOCK_MU_PLUGIN_HOOK_NAME, $my_mock_mu_plugin_callback);
Assert::assertTrue(remove_filter(WordPressMockBridge::MOCK_MU_PLUGIN_HOOK_NAME, $my_mock_mu_plugin_callback));
Assert::assertFalse(remove_filter(WordPressMockBridge::MOCK_MU_PLUGIN_HOOK_NAME, $my_mock_mu_plugin_callback));
add_filter(WordPressMockBridge::MOCK_MU_PLUGIN_HOOK_NAME, $my_mock_mu_plugin_callback);
