<?php

declare(strict_types=1);

namespace ElasticApmTests\TestsSharedCode;

use Elastic\Apm\Impl\ExecutionSegmentData;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Metadata;
use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\Impl\Util\ArrayUtil;
use ElasticApmTests\UnitTests\Util\NotFoundException;
use ElasticApmTests\Util\TestCaseBase;
use PHPUnit\Framework\TestCase;

final class EventsFromAgent implements LoggableInterface
{
    use LoggableTrait;

    /** @var Metadata[] */
    public $metadata = [];

    /** @var array<string, TransactionData> */
    public $idToTransaction = [];

    /** @var array<string, SpanData> */
    public $idToSpan = [];

    public function clear(): void
    {
        $this->metadata = [];
        $this->idToTransaction = [];
        $this->idToSpan = [];
    }

    /**
     * @return TransactionData
     */
    public function singleTransaction(): TransactionData
    {
        TestCase::assertCount(1, $this->idToTransaction);
        return $this->idToTransaction[array_key_first($this->idToTransaction)];
    }

    /**
     * @return SpanData
     */
    public function singleSpan(): SpanData
    {
        TestCase::assertCount(1, $this->idToSpan);
        return $this->idToSpan[array_key_first($this->idToSpan)];
    }

    public function executionSegmentByIdOrNull(string $id): ?ExecutionSegmentData
    {
        if (!is_null($span = ArrayUtil::getValueIfKeyExistsElse($id, $this->idToSpan, null))) {
            return $span;
        }
        return ArrayUtil::getValueIfKeyExistsElse($id, $this->idToTransaction, null);
    }

    public function executionSegmentById(string $id): ExecutionSegmentData
    {
        $result = $this->executionSegmentByIdOrNull($id);
        TestCaseBase::assertNotNull($result);
        return $result;
    }

    /**
     * @param string $name
     *
     * @return SpanData
     * @throws NotFoundException
     */
    public function spanByName(string $name): SpanData
    {
        foreach ($this->idToSpan as $id => $span) {
            if ($span->name === $name) {
                return $span;
            }
        }
        throw new NotFoundException("Span with the name `$name' not found");
    }

    /**
     * @param TransactionData $transaction
     *
     * @return array<string, SpanData>
     */
    public function spansForTransaction(TransactionData $transaction): array
    {
        $idToSpanFromTransaction = [];

        foreach ($this->idToSpan as $id => $span) {
            if ($span->transactionId === $transaction->id) {
                $idToSpanFromTransaction[$id] = $span;
            }
        }

        return $idToSpanFromTransaction;
    }

    /**
     * @param string              $parentId
     * @param ?iterable<SpanData> $spans
     *
     * @return iterable<SpanData>
     */
    public function findChildSpans(string $parentId, ?iterable $spans = null): iterable
    {
        /** @var SpanData $span */
        foreach ($spans ?? $this->idToSpan as $span) {
            if ($span->parentId === $parentId) {
                yield $span;
            }
        }
    }
}
