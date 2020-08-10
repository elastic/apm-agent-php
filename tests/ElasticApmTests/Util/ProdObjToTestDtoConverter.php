<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util;

use Elastic\Apm\ExecutionSegmentContextInterface;
use Elastic\Apm\ExecutionSegmentInterface;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\SpanContextInterface;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\TransactionContextInterface;
use Elastic\Apm\TransactionInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ProdObjToTestDtoConverter
{
    use StaticClassTrait;

    private static function convertExecutionSegmentContext(
        ExecutionSegmentContextInterface $prodObj,
        ExecutionSegmentContextTestDto $result
    ): void {
        foreach ($prodObj->getLabels() as $key => $value) {
            $result->setLabel($key, $value);
        }
    }

    private static function convertExecutionSegment(
        ExecutionSegmentInterface $prodObj,
        ExecutionSegmentTestDto $result
    ): void {
        $result->setDuration($prodObj->getDuration());
        $result->setId($prodObj->getId());
        $result->setName($prodObj->getName());
        $result->setTimestamp($prodObj->getTimestamp());
        $result->setTraceId($prodObj->getTraceId());
        $result->setType($prodObj->getType());
    }

    private static function convertSpanContext(SpanContextInterface $prodObj, SpanContextTestDto $result): void
    {
        self::convertExecutionSegmentContext($prodObj, $result);
    }

    public static function convertSpan(SpanInterface $prodObj): SpanTestDto
    {
        ValidationUtil::assertValidSpan($prodObj);

        if ($prodObj instanceof SpanTestDto) {
            return $prodObj;
        }

        $result = new SpanTestDto();

        self::convertExecutionSegment($prodObj, $result);

        $result->setAction($prodObj->getAction());
        self::convertSpanContext($prodObj->context(), $result->contextDto());
        $result->setParentId($prodObj->getParentId());
        $result->setStart($prodObj->getStart());
        $result->setSubtype($prodObj->getSubtype());
        $result->setTransactionId($prodObj->getTransactionId());

        return $result;
    }

    private static function convertTransactionContext(
        TransactionContextInterface $prodObj,
        TransactionContextTestDto $result
    ): void {
        self::convertExecutionSegmentContext($prodObj, $result);
    }

    public static function convertTransaction(TransactionInterface $prodObj): TransactionTestDto
    {
        ValidationUtil::assertValidTransaction($prodObj);

        if ($prodObj instanceof TransactionTestDto) {
            return $prodObj;
        }

        $result = new TransactionTestDto();

        self::convertExecutionSegment($prodObj, $result);

        self::convertTransactionContext($prodObj->context(), $result->contextDto());
        $result->setParentId($prodObj->getParentId());
        $result->setDroppedSpansCount($prodObj->getDroppedSpansCount());
        $result->setStartedSpansCount($prodObj->getStartedSpansCount());

        return $result;
    }
}
