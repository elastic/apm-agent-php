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
    /** @var int */
    protected $droppedSpansCount = 0;

    /** @var string|null */
    protected $parentId = null;

    /** @var int */
    protected $startedSpansCount = 0;

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

        parent::serializeProperty($propKey, $propValue, /* ref */ $result);
    }

    public function __toString(): string
    {
        return self::dataToString($this, 'TransactionData');
    }
}
