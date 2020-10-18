<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests;

use PHPUnit\Framework\TestCase;

const DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_NAMESPACE = __NAMESPACE__;
const DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_FILE_NAME = __FILE__;
const DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_LINE_NUMBER = 21;

/**
 * @param callable $callable
 *
 * @phpstan-param callable(): void $callable
 */
function dummyFuncForTestsWithNamespace(callable $callable): void
{
    TestCase::assertSame(DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_LINE_NUMBER, __LINE__ + 1);
    $callable(); // DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_LINE_NUMBER should be this line number
}
