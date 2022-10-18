<?php

/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

declare(strict_types=1);

namespace ElasticApmTests;

use PHPUnit\Framework\Assert;

const DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_NAMESPACE = __NAMESPACE__;
const DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_FILE_NAME = __FILE__;
const DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_LINE_NUMBER = 40;

/**
 * @param callable $callable
 *
 * @phpstan-param callable(): void $callable
 */
function dummyFuncForTestsWithNamespace(callable $callable): void
{
    Assert::assertSame(DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_LINE_NUMBER, __LINE__ + 1);
    $callable(); // DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CALLABLE_LINE_NUMBER should be this line number
}
