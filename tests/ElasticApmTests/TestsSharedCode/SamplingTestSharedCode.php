<?php

declare(strict_types=1);

namespace ElasticApmTests\TestsSharedCode;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use ElasticApmTests\Util\TestCaseBase;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SamplingTestSharedCode
{
    use StaticClassTrait;

    /**
     * @return iterable<float|null>
     */
    public static function rates(): iterable
    {
        yield from [null, 0.0, 0.001, 0.01, 0.1, 0.3, 0.5, 0.9, 1.0];
    }

    public static function appCodeForTwoNestedSpansTest(float $transactionSampleRate): void
    {
        $tx = ElasticApm::getCurrentTransaction();
        $expectedIsSampled = $transactionSampleRate === 1.0 ? true : ($transactionSampleRate === 0.0 ? false : null);
        if (!is_null($expectedIsSampled) && $tx->isSampled() !== $expectedIsSampled) {
            $tx->discard();
            throw new RuntimeException(
                "transactionSampleRate: $transactionSampleRate" .
                " expectedIsSampled: $expectedIsSampled" .
                " tx->isSampled: " . ($tx->isSampled() ? 'true' : 'false')
            );
        }

        $tx->context()->setLabel('TX_label_key', 123);
        $tx->captureCurrentSpan(
            'span1_name',
            'span1_type',
            function () use ($tx) {
                $tx->captureCurrentSpan(
                    'span11_name',
                    'span11_type',
                    function () {
                    }
                );
            }
        );
    }

    public static function assertResultsForTwoNestedSpansTest(
        ?float $transactionSampleRate,
        EventsFromAgent $eventsFromAgent
    ): void {
        $tx = $eventsFromAgent->singleTransaction();
        if (is_null($transactionSampleRate) || $transactionSampleRate === 1.0) {
            TestCase::assertTrue($tx->isSampled);
        } elseif ($transactionSampleRate === 0.0) {
            TestCase::assertFalse($tx->isSampled);
        }

        if ($tx->isSampled) {
            TestCase::assertCount(2, $eventsFromAgent->idToSpan);
            // Started and dropped spans should be counted only for sampled transactions
            TestCase::assertSame(2, $tx->startedSpansCount);

            TestCaseBase::assertLabelsCount(1, $tx);
            TestCase::assertSame(123, TestCaseBase::getLabel($tx, 'TX_label_key'));
        } else {
            TestCase::assertEmpty($eventsFromAgent->idToSpan);
            // Started and dropped spans should be counted only for sampled transactions
            TestCase::assertSame(0, $tx->startedSpansCount);

            TestCase::assertNull($tx->context);
        }
        TestCase::assertSame(0, $tx->droppedSpansCount);
    }
}
