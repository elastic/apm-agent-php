<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Log;

use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Tests\UnitTests\LogTests\LoggableTests;
use JsonSerializable;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class LoggableToJsonSerializableWrapper implements JsonSerializable
{
    /** @var LoggableInterface */
    private $loggable;

    public function __construct(LoggableInterface $loggable)
    {
        $this->loggable = $loggable;
    }

    /**
     * @return mixed
     *
     * Called by json_encode
     * @noinspection PhpUnused
     */
    public function jsonSerialize()
    {
        $logStreamToJson = new LogStreamToJson();
        $this->loggable->toLog($logStreamToJson);
        return $logStreamToJson->toJsonEncodeInput();
    }
}
