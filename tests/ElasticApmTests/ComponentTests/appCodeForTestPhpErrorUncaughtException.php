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

use Exception;
use PHPUnit\Framework\TestCase;
use Throwable;

const APP_CODE_FOR_TEST_PHP_ERROR_UNCAUGHT_EXCEPTION_MESSAGE = 'Message for uncaught exception';

function appCodeForTestPhpErrorUncaughtExceptionImpl2(): void
{
    throw new Exception('Message for caught exception');
}

const APP_CODE_FOR_TEST_PHP_ERROR_UNCAUGHT_EXCEPTION_ERROR_LINE_NUMBER = 47;

function appCodeForTestPhpErrorUncaughtExceptionImpl(): void
{
    try {
        appCodeForTestPhpErrorUncaughtExceptionImpl2();
    } catch (Throwable $throwable) {
    }

    TestCase::assertSame(APP_CODE_FOR_TEST_PHP_ERROR_UNCAUGHT_EXCEPTION_ERROR_LINE_NUMBER, __LINE__ + 1);
    throw new Exception(APP_CODE_FOR_TEST_PHP_ERROR_UNCAUGHT_EXCEPTION_MESSAGE);
}

const APP_CODE_FOR_TEST_PHP_ERROR_UNCAUGHT_EXCEPTION_CALL_TO_IMPL_LINE_NUMBER = 55;

function appCodeForTestPhpErrorUncaughtException(): void
{
    TestCase::assertSame(APP_CODE_FOR_TEST_PHP_ERROR_UNCAUGHT_EXCEPTION_CALL_TO_IMPL_LINE_NUMBER, __LINE__ + 1);
    appCodeForTestPhpErrorUncaughtExceptionImpl();
}
