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

namespace ElasticApmTests\ComponentTests;

use PHPUnit\Framework\TestCase;

const APP_CODE_FOR_TEST_PHP_ERROR_UNDEFINED_VARIABLE_ERROR_LINE_NUMBER = 38;
const APP_CODE_FOR_TEST_PHP_ERROR_UNDEFINED_VARIABLE_CALL_TO_IMPL_LINE_NUMBER = 44;

function appCodeForTestPhpErrorUndefinedVariableImpl(): void
{
    // Ensure E_NOTICE is included in error_reporting
    error_reporting(error_reporting() | E_NOTICE);

    TestCase::assertSame(APP_CODE_FOR_TEST_PHP_ERROR_UNDEFINED_VARIABLE_ERROR_LINE_NUMBER, __LINE__ + 2);
    /** @noinspection PhpUndefinedVariableInspection */
    $undefinedVariable = $undefinedVariable + 1; // @phpstan-ignore-line
}

function appCodeForTestPhpErrorUndefinedVariable(): void
{
    TestCase::assertSame(APP_CODE_FOR_TEST_PHP_ERROR_UNDEFINED_VARIABLE_CALL_TO_IMPL_LINE_NUMBER, __LINE__ + 1);
    appCodeForTestPhpErrorUndefinedVariableImpl();
}
