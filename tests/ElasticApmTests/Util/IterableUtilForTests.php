<?php

/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

declare(strict_types=1);

namespace ElasticApmTests\Util;

use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Generator;
use Iterator;
use PHPUnit\Framework\Assert;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class IterableUtilForTests
{
    use StaticClassTrait;

    public const ALL_BOOL_VALUES = [true, false];

    /**
     * @param iterable<mixed> $iterable
     *
     * @return int
     */
    public static function count(iterable $iterable): int
    {
        $result = 0;
        foreach ($iterable as $ignored) {
            ++$result;
        }
        return $result;
    }

    /**
     * @param iterable<mixed> $iterable
     *
     * @return bool
     */
    public static function isEmpty(iterable $iterable): bool
    {
        /** @noinspection PhpLoopNeverIteratesInspection */
        foreach ($iterable as $ignored) {
            return false;
        }
        return true;
    }

    /**
     * @param iterable<mixed> $iterable
     * @param mixed          &$valOut
     *
     * @return bool
     */
    public static function getFirstValue(iterable $iterable, /* out */ &$valOut): bool
    {
        /** @noinspection PhpLoopNeverIteratesInspection */
        foreach ($iterable as $val) {
            $valOut = $val;
            return true;
        }
        return false;
    }

    /**
     * @param iterable<mixed, mixed> $iterable
     *
     * @return iterable<mixed, mixed>
     */
    public static function skipFirst(iterable $iterable): iterable
    {
        $isFirst = true;
        foreach ($iterable as $key => $val) {
            if ($isFirst) {
                $isFirst = false;
                continue;
            }
            yield $key => $val;
        }
    }

    /**
     * @param iterable<mixed> $iterable
     *
     * @return array<mixed>
     */
    public static function toList(iterable $iterable): array
    {
        if (is_array($iterable)) {
            return $iterable;
        }

        $result = [];
        foreach ($iterable as $val) {
            $result[] = $val;
        }
        return $result;
    }

    /**
     * @param iterable<mixed> $inputIterable
     *
     * @return Generator<mixed>
     */
    public static function iterableToGenerator(iterable $inputIterable): Generator
    {
        foreach ($inputIterable as $val) {
            yield $val;
        }
    }

    /**
     * @param iterable<mixed> $inputIterable
     *
     * @return Iterator<mixed>
     */
    public static function iterableToIterator(iterable $inputIterable): Iterator
    {
        if ($inputIterable instanceof Iterator) {
            return $inputIterable;
        }

        return self::iterableToGenerator($inputIterable);
    }

    /**
     * @param iterable<mixed> $iterables
     *
     * @return Generator<mixed[]>
     */
    public static function zip(iterable ...$iterables): Generator
    {
        if (ArrayUtil::isEmpty($iterables)) {
            return;
        }

        /** @var Iterator<mixed>[] $iterators */
        $iterators = [];
        foreach ($iterables as $inputIterable) {
            $iterator = self::iterableToIterator($inputIterable);
            $iterator->rewind();
            $iterators[] = $iterator;
        }

        while (true) {
            $tuple = [];
            foreach ($iterators as $iterator) {
                if ($iterator->valid()) {
                    $tuple[] = $iterator->current();
                    $iterator->next();
                } else {
                    Assert::assertTrue(ArrayUtil::isEmpty($tuple));
                }
            }

            if (ArrayUtil::isEmpty($tuple)) {
                return;
            }

            Assert::assertSame(count($iterables), count($tuple));
            yield $tuple;
        }
    }
}
