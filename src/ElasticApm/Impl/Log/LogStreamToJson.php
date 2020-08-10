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
final class LogStreamToJson implements LogStreamInterface
{
    /** @var array<mixed> */
    private $rootElements = [];

    /**
     * @param array<mixed> $list
     */
    public function writeList(array $list): void
    {
        $rootElements[] = $list;
    }

    /**
     * @param array<string, mixed> $map
     */
    public function writeMap(array $map): void
    {
        $rootElements[] = $map;
    }

    /**
     * @return mixed
     *
     * Called by json_encode
     * @noinspection PhpUnused
     */
    public function toJsonEncodeInput()
    {
        if (empty($this->rootElements)) {
            return [];
        }

        if (count($this->rootElements) === 1) {
            return LogToJsonUtil::convert($this->rootElements[0]);
        }

        return LogToJsonUtil::convert($this->rootElements);
    }
}
