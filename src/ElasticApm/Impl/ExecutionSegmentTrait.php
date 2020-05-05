<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Closure;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\IdGenerator;
use Elastic\Apm\Impl\Util\TimeUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
trait ExecutionSegmentTrait
{
    /** @var Tracer */
    protected $tracer;

    /** @var float */
    private $durationOnBegin;

    /** @var float Monotonic time since some unspecified starting point, in microseconds */
    private $monotonicBeginTime;

    /** @var Logger */
    private $logger;

    /** @var bool */
    private $isEnded = false;

    protected function constructExecutionSegmentTrait(
        Tracer $tracer,
        string $traceId,
        string $name,
        string $type,
        ?float $timestamp = null
    ): void {
        $systemClockCurrentTime = $this->timestamp = $tracer->getClock()->getSystemClockCurrentTime();
        if ($timestamp === null) {
            $this->timestamp = $systemClockCurrentTime;
        } else {
            $this->timestamp = $timestamp;
        }
        $this->durationOnBegin = TimeUtil::calcDuration($this->timestamp, $systemClockCurrentTime);
        $this->monotonicBeginTime = $tracer->getClock()->getMonotonicClockCurrentTime();
        $this->tracer = $tracer;
        $this->traceId = $traceId;
        $this->id = IdGenerator::generateId(IdGenerator::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES);
        $this->setName($name);
        $this->setType($type);
        $this->logger = $this->createLogger(__CLASS__, __FILE__);
    }

    protected function createLogger(string $className, string $sourceCodeFile): Logger
    {
        $logger = $this->tracer->ambientContext()->loggerFactory->loggerForClass($className, $sourceCodeFile);
        $logger->addKeyValueToAttachedContext('Id', $this->getId());
        $logger->addKeyValueToAttachedContext('TraceId', $this->getId());
        return $logger;
    }

    /** @inheritDoc */
    public function captureChildSpan(
        string $name,
        string $type,
        Closure $callback,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ) {
        $newSpan = $this->beginChildSpan($name, $type, $subtype, $action, $timestamp);
        try {
            return $callback($newSpan);
        } finally {
            $newSpan->end();
        }
    }

    protected function endExecutionSegment(?float $duration = null): bool
    {
        if ($this->isEnded) {
            return false;
        }

        if ($duration === null) {
            $monotonicEndTime = $this->tracer->getClock()->getMonotonicClockCurrentTime();
            $this->duration = $this->durationOnBegin
                              + TimeUtil::calcDuration($this->monotonicBeginTime, $monotonicEndTime);
            if ($this->duration < 0) {
                $this->duration = 0;
            }
        } else {
            $this->duration = $duration;
        }

        $this->isEnded = true;
        return true;
    }

    /** @inheritDoc */
    public function hasEnded(): bool
    {
        return $this->isEnded;
    }

    public function isNoop(): bool
    {
        return false;
    }

    public function getTracer(): Tracer
    {
        return $this->tracer;
    }

    public function setName(string $name): void
    {
        $this->name = $this->tracer->limitKeywordString($name);
    }

    public function setType(string $type): void
    {
        $this->type = $this->tracer->limitKeywordString($type);
    }

    public function setLabel(string $key, $value): void
    {
        if (!ExecutionSegmentData::doesValueHaveSupportedLabelType($value)) {
            ($loggerProxy = $this->logger->ifEnabledError())
            && $loggerProxy->log(
                'Value for label is of unsupported type - it will be discarded',
                ['value type' => DbgUtil::getType($value), 'key' => $key, 'value' => $value],
                __LINE__,
                __METHOD__
            );
            return;
        }

        $this->labels[$this->tracer->limitKeywordString($key)] = is_string($value)
            ? $this->tracer->limitKeywordString($value)
            : $value;
    }
}
