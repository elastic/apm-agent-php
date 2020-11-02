<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\TestsSharedCode;

use Elastic\Apm\ExecutionSegmentDataInterface;
use Elastic\Apm\Impl\MetadataInterface;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\ObjectToStringUsingPropertiesTrait;
use Elastic\Apm\SpanDataInterface;
use Elastic\Apm\Tests\UnitTests\Util\NotFoundException;
use Elastic\Apm\Tests\Util\TestCaseBase;
use Elastic\Apm\TransactionDataInterface;
use PHPUnit\Framework\TestCase;

final class EventsFromAgent
{
    use ObjectToStringUsingPropertiesTrait;

    /** @var MetadataInterface[] */
    public $metadata = [];

    /** @var array<string, TransactionDataInterface> */
    public $idToTransaction = [];

    /** @var array<string, SpanDataInterface> */
    public $idToSpan = [];

    public function clear(): void
    {
        $this->metadata = [];
        $this->idToTransaction = [];
        $this->idToSpan = [];
    }

    /**
     * @return TransactionDataInterface
     */
    public function singleTransaction(): TransactionDataInterface
    {
        TestCase::assertCount(1, $this->idToTransaction);
        return $this->idToTransaction[array_key_first($this->idToTransaction)];
    }

    /**
     * @return SpanDataInterface
     */
    public function singleSpan(): SpanDataInterface
    {
        TestCase::assertCount(1, $this->idToSpan);
        return $this->idToSpan[array_key_first($this->idToSpan)];
    }

    public function executionSegmentByIdOrNull(string $id): ?ExecutionSegmentDataInterface
    {
        if (!is_null($span = ArrayUtil::getValueIfKeyExistsElse($id, $this->idToSpan, null))) {
            return $span;
        }
        return ArrayUtil::getValueIfKeyExistsElse($id, $this->idToTransaction, null);
    }

    public function executionSegmentById(string $id): ExecutionSegmentDataInterface
    {
        $result = $this->executionSegmentByIdOrNull($id);
        TestCaseBase::assertNotNull($result);
        return $result;
    }

    /**
     * @param string $name
     *
     * @return SpanDataInterface
     * @throws NotFoundException
     */
    public function spanByName(string $name): SpanDataInterface
    {
        foreach ($this->idToSpan as $id => $span) {
            if ($span->getName() === $name) {
                return $span;
            }
        }
        throw new NotFoundException("Span with the name `$name' not found");
    }

    /**
     * @param TransactionDataInterface $transaction
     *
     * @return array<string, SpanDataInterface>
     */
    public function spansForTransaction(TransactionDataInterface $transaction): array
    {
        $idToSpanFromTransaction = [];

        foreach ($this->idToSpan as $id => $span) {
            if ($span->getTransactionId() === $transaction->getId()) {
                $idToSpanFromTransaction[$id] = $span;
            }
        }

        return $idToSpanFromTransaction;
    }

    /**
     * @param string                       $parentId
     * @param ?iterable<SpanDataInterface> $spans
     *
     * @return iterable<SpanDataInterface>
     */
    public function findChildSpans(string $parentId, ?iterable $spans = null): iterable
    {
        /** @var SpanDataInterface $span */
        foreach ($spans ?? $this->idToSpan as $span) {
            if ($span->getParentId() === $parentId) {
                yield $span;
            }
        }
    }
}
