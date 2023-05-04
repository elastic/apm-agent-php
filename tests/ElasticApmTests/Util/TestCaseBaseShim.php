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

namespace ElasticApmTests\Util;

use Exception;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

class TestCaseBaseShim extends TestCase
{
    protected static function addMessageStackToException(Exception $ex): void
    {
        AssertMessageStackExceptionHelper::setMessage($ex, $ex->getMessage() . "\n" . 'AssertMessageStack:' . "\n" . AssertMessageStack::formatScopesStackAsString());
    }

    /**
     * @inheritDoc
     *
     * @param mixed $expected
     * @param mixed $actual
     *
     * @noinspection PhpSignatureMismatchDuringInheritanceInspection
     */
    public static function assertNotEquals($expected, $actual, string $message = ''): void
    {
        try {
            Assert::assertNotEquals($expected, $actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param mixed $expected
     * @param mixed $actual
     *
     * @noinspection PhpSignatureMismatchDuringInheritanceInspection
     */
    public static function assertEquals($expected, $actual, string $message = ''): void
    {
        try {
            Assert::assertEquals($expected, $actual, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }

    /**
     * @inheritDoc
     *
     * @param mixed           $needle
     * @param iterable<mixed> $haystack
     *
     * @noinspection PhpSignatureMismatchDuringInheritanceInspection
     */
    public static function assertContains($needle, $haystack, string $message = ''): void
    {
        try {
            Assert::assertContains($needle, $haystack, $message);
        } catch (AssertionFailedError $ex) {
            self::addMessageStackToException($ex);
            throw $ex;
        }
    }
}
