<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\TestsSharedCode\TransactionMaxSpansTest;

use Ds\Stack;
use Elastic\Apm\ExecutionSegmentInterface;
use Elastic\Apm\Impl\NoopSpan;
use Elastic\Apm\Impl\Util\ObjectToStringUsingPropertiesTrait;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\Tests\Util\RandomUtilForTests;
use Elastic\Apm\TransactionInterface;
use PHPUnit\Framework\TestCase;

final class AppCode
{
    use ObjectToStringUsingPropertiesTrait;

    private const MAX_RECURSION_DEPTH = 10;

    private const BEGIN_CURRENT_SPAN_API_ID = 'beginCurrentSpan';
    private const CAPTURE_CURRENT_SPAN_API_ID = 'captureCurrentSpan';
    private const BEGIN_CHILD_SPAN_API_ID = 'beginChildSpan';
    private const CAPTURE_CHILD_SPAN_API_ID = 'captureChildSpan';

    public const NUMBER_OF_CHILD_SPANS_LABEL_KEY = 'number_of_child_spans';

    /** @var Args */
    private $testArgs;

    /** @var TransactionInterface */
    private $tx;

    /** @var int */
    private $currentRecursionDepth = 0;

    /** @var Stack<SpanInfo> */
    private $spanInfoStack;

    /** @var int */
    private $numberOfSpansCreated = 0;

    public function __construct(Args $testArgs, TransactionInterface $tx)
    {
        $this->testArgs = $testArgs;
        $this->tx = $tx;
        $this->spanInfoStack = new Stack();
        $this->initLabelAndPushToStack(NoopSpan::singletonInstance(), 'span', /* needsExplicitEndCall */ false);
    }

    public static function run(Args $testArgs, TransactionInterface $tx): void
    {
        TestCase::assertGreaterThanOrEqual(0, $testArgs->numberOfSpansToCreate);
        TestCase::assertGreaterThan(0, $testArgs->maxFanOut);
        TestCase::assertGreaterThan(0, $testArgs->maxDepth);
        (new self($testArgs, $tx))->runLoop();
    }

    private function runLoop(): void
    {
        $depthOnEntry = $this->actualSpansDepth();
        $isTraversingDown = true;
        while (true) {
            TestCase::assertLessThanOrEqual($this->testArgs->numberOfSpansToCreate, $this->numberOfSpansCreated);
            TestCase::assertLessThanOrEqual($this->testArgs->maxDepth, $this->actualSpansDepth());
            TestCase::assertGreaterThanOrEqual($depthOnEntry, $this->actualSpansDepth());
            if ($this->actualSpansDepth() > 0) {
                TestCase::assertLessThanOrEqual($this->testArgs->maxFanOut, $this->topSpanInfo()->childCount);
            }

            if ($isTraversingDown) {
                if (
                    ($this->numberOfSpansCreated === $this->testArgs->numberOfSpansToCreate)
                    || ($this->actualSpansDepth() === $this->testArgs->maxDepth)
                    || (($this->actualSpansDepth() > 0)
                        && ($this->topSpanInfo()->childCount === $this->testArgs->maxFanOut))
                ) {
                    $isTraversingDown = false;
                    continue;
                }
            } else {
                $reachedEntrySpan = ($this->actualSpansDepth() === $depthOnEntry);
                if ($this->topSpanInfo()->needsExplicitEndCall) {
                    $this->topSpanInfo()->span->end();
                }
                $this->spanInfoStack->pop();
                if ($reachedEntrySpan) {
                    break;
                }

                $isTraversingDown = true;
                continue;
            }

            $this->createSpan();
        }
    }

    private function actualSpansDepth(): int
    {
        // actualSpansDepth is spanInfoStack->count() - 1 because the bottom entry in spanInfoStack
        // represents the transaction itself
        TestCase::assertGreaterThanOrEqual(1, $this->spanInfoStack->count());
        return $this->spanInfoStack->count() - 1;
    }

    private function topSpanInfo(): SpanInfo
    {
        TestCase::assertFalse($this->spanInfoStack->isEmpty());
        return $this->spanInfoStack->peek();
    }

    /**
     * @param string        $apiId
     * @param array<string> $apis
     */
    private function addRecursionApi(string $apiId, array &$apis): void
    {
        if ($this->currentRecursionDepth < self::MAX_RECURSION_DEPTH) {
            $apis[] = $apiId;
        }
    }

    private function initLabelAndPushToStack(SpanInterface $span, string $name, bool $needsExplicitEndCall): void
    {
        $execSegmentToSetLabel = $this->spanInfoStack->isEmpty() ? $this->tx : $span;
        $execSegmentToSetLabel->setLabel(self::NUMBER_OF_CHILD_SPANS_LABEL_KEY, 0);
        $this->spanInfoStack->push(new SpanInfo($span, $name, $needsExplicitEndCall));
    }

    private function createSpan(): void
    {
        ++$this->numberOfSpansCreated;
        ++$this->topSpanInfo()->childCount;
        /** @var ExecutionSegmentInterface $topExecSegment */
        $topExecSegment = ($this->actualSpansDepth() === 0) ? $this->tx : $this->topSpanInfo()->span;
        $topExecSegment->setLabel(self::NUMBER_OF_CHILD_SPANS_LABEL_KEY, $this->topSpanInfo()->childCount);
        $spanName = $this->topSpanInfo()->name . strval($this->topSpanInfo()->childCount);

        $recursiveCallback = function (SpanInterface $span) use ($spanName) {
            ++$this->currentRecursionDepth;
            $this->initLabelAndPushToStack($span, $spanName, /* needsExplicitEndCall */ false);
            $this->runLoop();
            --$this->currentRecursionDepth;
        };

        $createSpanApis = [];

        $isTxCurrentSpanTopSpanInfo = ($this->tx->getCurrentSpan()->getId() === $this->topSpanInfo()->span->getId());
        if ($isTxCurrentSpanTopSpanInfo) {
            $createSpanApis += [self::BEGIN_CURRENT_SPAN_API_ID];
            $this->addRecursionApi(self::CAPTURE_CURRENT_SPAN_API_ID, /* ref */ $createSpanApis);
        }

        if ($this->testArgs->shouldUseOnlyCurrentCreateSpanApis) {
            TestCase::assertTrue($isTxCurrentSpanTopSpanInfo);
        } else {
            $createSpanApis += [self::BEGIN_CHILD_SPAN_API_ID];
            $this->addRecursionApi(self::CAPTURE_CHILD_SPAN_API_ID, /* ref */ $createSpanApis);
        }

        $apiIndex = $this->numberOfSpansCreated % count($createSpanApis);
        switch ($createSpanApis[$apiIndex]) {
            case self::BEGIN_CHILD_SPAN_API_ID:
                $span = $topExecSegment->beginChildSpan($spanName, 'test_span_type');
                $this->initLabelAndPushToStack($span, $spanName, /* needsExplicitEndCall */ true);
                break;

            case self::CAPTURE_CHILD_SPAN_API_ID:
                $topExecSegment->captureChildSpan($spanName, 'test_span_type', $recursiveCallback);
                break;

            case self::BEGIN_CURRENT_SPAN_API_ID:
                $span = $this->tx->beginCurrentSpan($spanName, 'test_span_type');
                $this->initLabelAndPushToStack($span, $spanName, /* needsExplicitEndCall */ true);
                break;

            case self::CAPTURE_CURRENT_SPAN_API_ID:
                $this->tx->captureCurrentSpan($spanName, 'test_span_type', $recursiveCallback);
                break;

            default:
                TestCase::fail("This point should never be reached. this: $this");
        }
    }
}
