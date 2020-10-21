<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\TransactionDataInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class TransactionData extends ExecutionSegmentData implements TransactionDataInterface
{
    /** @var string|null */
    protected $parentId = null;

    /** @var int */
    protected $startedSpansCount = 0;

    /** @var int */
    protected $droppedSpansCount = 0;

    /** @var string|null */
    protected $result = null;

    /** @var bool */
    protected $isSampled;

    public function getDroppedSpansCount(): int
    {
        return $this->droppedSpansCount;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function getStartedSpansCount(): int
    {
        return $this->startedSpansCount;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    /**
     * Transactions that are 'sampled' will include all available information
     * Transactions that are not sampled will not have 'spans' or 'context'.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L72
     */
    public function isSampled(): bool
    {
        return $this->isSampled;
    }

    /**
     * @param array<string, mixed> $result
     */
    protected function serializeSpanCount(array &$result): void
    {
        $spanCountSubObject = ['started' => $this->getStartedSpansCount()];
        if ($this->getDroppedSpansCount() != 0) {
            $spanCountSubObject['dropped'] = $this->getDroppedSpansCount();
        }

        parent::serializeProperty('span_count', $spanCountSubObject, /* ref */ $result);
    }

    /**
     * @param string               $propKey
     * @param mixed                $propValue
     * @param array<string, mixed> $result
     */
    protected function serializeProperty(string $propKey, $propValue, array &$result): void
    {
        // Don't serialize properties added by a derived class
        if (!property_exists(__CLASS__, $propKey)) {
            return;
        }

        if ($propKey === 'droppedSpansCount') {
            return;
        }

        if ($propKey === 'startedSpansCount') {
            $this->serializeSpanCount(/* ref */ $result);
            return;
        }

        if ($propKey === 'isSampled') {
            parent::serializeProperty('sampled', $this->isSampled, /* ref */ $result);
            return;
        }

        parent::serializeProperty($propKey, $propValue, /* ref */ $result);
    }

    protected function shouldSerializeContext(): bool
    {
        return $this->isSampled && parent::shouldSerializeContext();
    }

    protected static function getterMethodNameForConvertToData(string $propKey): string
    {
        if ($propKey === 'isSampled') {
            return 'isSampled';
        }

        return parent::getterMethodNameForConvertToData($propKey);
    }
}
