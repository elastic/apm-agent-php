<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\ElasticApm;
use ElasticApmTests\UnitTests\Util\NotFoundException;
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
