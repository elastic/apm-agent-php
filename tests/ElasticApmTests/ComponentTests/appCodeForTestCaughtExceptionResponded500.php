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

use ElasticApmTests\ComponentTests\Util\HttpConstantsForTests;
use ElasticApmTests\Util\DummyExceptionForTests;
use Exception;
use PHPUnit\Framework\TestCase;
use Throwable;

const APP_CODE_FOR_TEST_CAUGHT_EXCEPTION_RESPONDED_500_MESSAGE = 'Message for caught exception responded 500';

function appCodeForTestCaughtExceptionResponded500Impl2(): void
{
    throw new Exception('Message for the first caught exception');
}

const APP_CODE_FOR_TEST_CAUGHT_EXCEPTION_RESPONDED_500_THROW_LINE_NUMBER = 52;
const APP_CODE_FOR_TEST_CAUGHT_EXCEPTION_RESPONDED_500_CODE = 12345;

function appCodeForTestCaughtExceptionResponded500Impl(): void
{
    try {
        appCodeForTestCaughtExceptionResponded500Impl2();
    } catch (Throwable $throwable) {
    }

    $message = APP_CODE_FOR_TEST_CAUGHT_EXCEPTION_RESPONDED_500_MESSAGE;
    $code = APP_CODE_FOR_TEST_CAUGHT_EXCEPTION_RESPONDED_500_CODE;
    TestCase::assertSame(APP_CODE_FOR_TEST_CAUGHT_EXCEPTION_RESPONDED_500_THROW_LINE_NUMBER, __LINE__ + 1);
    throw new DummyExceptionForTests($message, $code);
}

const APP_CODE_FOR_TEST_CAUGHT_EXCEPTION_RESPONDED_500_CALL_TO_IMPL_LINE_NUMBER = 61;

function appCodeForTestCaughtExceptionResponded500(): void
{
    TestCase::assertSame(APP_CODE_FOR_TEST_CAUGHT_EXCEPTION_RESPONDED_500_CALL_TO_IMPL_LINE_NUMBER, __LINE__ + 2);
    try {
        appCodeForTestCaughtExceptionResponded500Impl();
    } catch (Throwable $throwable) {
        http_response_code(HttpConstantsForTests::STATUS_INTERNAL_SERVER_ERROR);
    }
}
