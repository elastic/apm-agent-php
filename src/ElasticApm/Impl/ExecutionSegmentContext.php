<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\ExecutionSegmentContextInterface;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\SerializationUtil;
use JsonSerializable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class ExecutionSegmentContext implements ExecutionSegmentContextInterface, JsonSerializable
{
    /** @var bool */
    private $isFrozen = false;

    /** @var array<string, string|bool|int|float|null> */
    private $labels = [];

    /** @var Logger */
    private $logger;

    public function __construct(LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory->loggerForClass(LogCategory::PUBLIC_API, __NAMESPACE__, __CLASS__, __FILE__);
    }

    public function isNoop(): bool
    {
        return false;
    }

    private function checkIfAlreadyEnded(string $calledMethodName): bool
    {
        // return $this->executionSegment->checkIfAlreadyEnded($calledMethodName);
        return false;
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    public static function doesValueHaveSupportedLabelType($value): bool
    {
        return is_null($value) || is_string($value) || is_bool($value) || is_int($value) || is_float($value);
    }

    public function setLabel(string $key, $value): void
    {
        if ($this->checkIfAlreadyEnded(__FUNCTION__)) {
            return;
        }

        if (!self::doesValueHaveSupportedLabelType($value)) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Value for label is of unsupported type - it will be discarded',
                ['value type' => DbgUtil::getType($value), 'key' => $key, 'value' => $value]
            );
            return;
        }

        $this->labels[Util\TextUtil::limitKeywordString($key)] = is_string($value)
            ? Util\TextUtil::limitKeywordString($value)
            : $value;
    }

    public function getLabels(): array
    {
        return $this->labels;
    }

    public function isEmpty(): bool
    {
        return empty($this->labels);
    }

    /**
     * @return array<string, mixed>
     *
     * Called by json_encode
     * @noinspection PhpUnused
     */
    public function jsonSerialize(): array
    {
        return SerializationUtil::buildJsonSerializeResult(
            [
                'tags' => empty($this->labels) ? null : $this->labels,
            ]
        );
    }
}
