<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Log;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class LoggableArray implements LoggableInterface
{
    private const COUNT_KEY = 'count';
    private const ARRAY_TYPE = 'array';

    /** @var array<mixed, mixed> */
    private $wrappedArray;

    /**
     * @param array<mixed, mixed> $wrappedArray
     */
    public function __construct(array $wrappedArray)
    {
        $this->wrappedArray = $wrappedArray;
    }

    public function toLog(LogStreamInterface $stream): void
    {
        if ($stream->isLastLevel()) {
            $stream->toLogAs(
                [LogConsts::TYPE_KEY => self::ARRAY_TYPE, self::COUNT_KEY => count($this->wrappedArray)]
            );
            return;
        }

        $stream->toLogAs(
            [LogConsts::TYPE_KEY => self::ARRAY_TYPE, self::COUNT_KEY => count($this->wrappedArray)]
        );
    }
}
