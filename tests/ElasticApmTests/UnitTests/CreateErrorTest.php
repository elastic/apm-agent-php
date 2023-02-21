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

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use ElasticApmTests\UnitTests\Util\TracerUnitTestCaseBase;

class CreateErrorTest extends TracerUnitTestCaseBase
{
    public function testFromThrowable(): void
    {
        // Arrange

        // Act

        // Assert
        $this->assertSame(1, 1);
    }

    public function testFromThrowableGetCodeReturnsInt(): void
    {
        // Arrange

        // Act

        // Assert
        $this->assertSame(1, 1);
    }

    public function testFromThrowableGetCodeReturnsString(): void
    {
        // Arrange

        // Act

        // Assert
        $this->assertSame(1, 1);
    }

    public function testFromThrowableGetCodeReturnsArray(): void
    {
        // Arrange

        // Act

        // Assert
        $this->assertSame(1, 1);
    }

    public function testFromThrowableGetCodeReturnsObject(): void
    {
        // Arrange

        // Act

        // Assert
        $this->assertSame(1, 1);
    }
}
