<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util\Deserialization;

use Elastic\Apm\StacktraceFrame;
use Elastic\Apm\Tests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class StacktraceDeserializer
{
    /**
     *
     * @param array<mixed, mixed> $deserializedRawData
     *
     * @return StacktraceFrame[]
     */
    public static function deserialize(array $deserializedRawData): array
    {
        /** @var \Elastic\Apm\StacktraceFrame[] */
        $frames = [];
        /** @var int */
        $nextExpectedIndex = 0;

        foreach ($deserializedRawData as $key => $value) {
            ValidationUtil::assertThat($key === $nextExpectedIndex);
            $frames[] = self::deserializeFrame($value);
            ++$nextExpectedIndex;
        }

        ValidationUtil::assertValidStacktrace($frames);

        return $frames;
    }

    /**
     * @param array<string, mixed> $deserializedRawData
     */
    private static function deserializeFrame(array $deserializedRawData): StacktraceFrame
    {
        /** @var string|null */
        $filename = null;
        /** @var int */
        $lineNumber = -1;
        /** @var string|null */
        $function = null;

        foreach ($deserializedRawData as $key => $value) {
            switch ($key) {
                case 'filename':
                    $filename = ValidationUtil::assertValidStacktraceFrameFilename($value);
                    break;

                case 'function':
                    $function = ValidationUtil::assertValidStacktraceFrameFilename($value);
                    break;

                case 'lineno':
                    $lineNumber = ValidationUtil::assertValidStacktraceFrameLineNumber($value);
                    break;

                default:
                    throw EventDataDeserializer::buildException("Unknown key: span_count->`$key'");
            }
        }

        ValidationUtil::assertThat(!is_null($filename));
        assert(!is_null($filename));
        ValidationUtil::assertThat($lineNumber !== -1);

        $result = new StacktraceFrame($filename, $lineNumber);
        $result->function = $function;

        return $result;
    }
}
