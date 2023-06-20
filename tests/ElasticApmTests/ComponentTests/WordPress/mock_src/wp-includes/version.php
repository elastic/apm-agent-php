<?php

use ElasticApmTests\ComponentTests\WordPress\WordPressMockBridge;
use ElasticApmTests\Util\TestCaseBase;

if (WordPressMockBridge::$expectedWordPressVersion !== null) {
    /**
     * @global mixed $wp_version
     */
    global $wp_version;
    $wp_version = WordPressMockBridge::$expectedWordPressVersion;

    $testFunc = function (): void {
        global $wp_version;
        TestCaseBase::assertTrue(isset($wp_version));
        TestCaseBase::assertSame(WordPressMockBridge::$expectedWordPressVersion, $wp_version);
    };
    $testFunc();
} else {
    global $wp_version;
    TestCaseBase::assertFalse(isset($wp_version));
}
