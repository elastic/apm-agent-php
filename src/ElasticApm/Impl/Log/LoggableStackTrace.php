<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Log;

use Elastic\Apm\Impl\Util\ArrayUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class LoggableStackTrace implements LoggableInterface
{
    /** @var array<mixed> */
    private $stackFrames;

    /** @var int */
    private $numberOfStackFramesToSkip;

    /** @var bool */
    private $includeValues;

    /**
     * @param int          $numberOfStackFramesToSkip
     * @param bool         $includeValues
     */
    public static function current(int $numberOfStackFramesToSkip = 0, bool $includeValues = true): LoggableStackTrace
    {
        return new LoggableStackTrace(
            debug_backtrace($includeValues ? DEBUG_BACKTRACE_PROVIDE_OBJECT : DEBUG_BACKTRACE_IGNORE_ARGS),
            $numberOfStackFramesToSkip + 1,
            $includeValues
        );
    }

    /**
     * @param array<mixed> $stackFrames
     * @param int          $numberOfStackFramesToSkip
     * @param bool         $includeValues
     */
    public function __construct(array $stackFrames, int $numberOfStackFramesToSkip = 0, bool $includeValues = true)
    {
        $this->stackFrames = $stackFrames;
        $this->numberOfStackFramesToSkip = $numberOfStackFramesToSkip;
        $this->includeValues = $includeValues;
    }

    public function toLog(LogStreamInterface $logStream): void
    {
        $convertFrames = [];
        $index = 0;
        foreach ($this->stackFrames as $stackFrame) {
            if ($index >= $this->numberOfStackFramesToSkip) {
                $convertFrames[] = $this->convertStackFrame($stackFrame);
            }
            ++$index;
        }
        $logStream->writeList($convertFrames);
    }

    /**
     * @param array<mixed> $stackFrame
     *
     * @return array<string, mixed>
     */
    private function convertStackFrame(array $stackFrame): array
    {
        $result = [];

        $getValueIfExists = function (string $key) use ($stackFrame, $result) {
            if (array_key_exists($key, $stackFrame)) {
                $result[$key] = LogToJsonUtil::convert($stackFrame[$key]);
            }
        };

        foreach (['class', 'function', 'file', 'line'] as $key) {
            $getValueIfExists($key);
        }

        if ($this->includeValues) {
            foreach (['object', 'args'] as $key) {
                $getValueIfExists($key);
            }
        }

        return $result;
    }
}
