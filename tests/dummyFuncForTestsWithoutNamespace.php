<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

const DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_CALLABLE_FILE_NAME = __FILE__;
const DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_CALLABLE_LINE_NUMBER = 18;

/**
 * @param callable $callable
 *
 * @phpstan-param callable(): void $callable
 */
function dummyFuncForTestsWithoutNamespace(callable $callable): void
{
    TestCase::assertSame(DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_CALLABLE_LINE_NUMBER, __LINE__ + 1);
    $callable(); // DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_CALLABLE_LINE_NUMBER should be this line number
}
