<?php

/** @noinspection PhpUnusedPrivateFieldInspection */

declare(strict_types=1);

namespace ElasticApmTests\UnitTests\LogTests;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LogStreamInterface;
use RuntimeException;

class ObjectThrowingInToLog implements LoggableInterface
{
    public function toLog(LogStreamInterface $stream): void
    {
        throw new RuntimeException('Dummy thrown on purpose');
    }
}
