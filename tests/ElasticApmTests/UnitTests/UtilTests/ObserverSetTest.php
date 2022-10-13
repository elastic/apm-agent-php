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

namespace ElasticApmTests\UnitTests\UtilTests;

use Elastic\Apm\Impl\Util\ObserverSet;
use ElasticApmTests\Util\TestCaseBase;

class ObserverSetTest extends TestCaseBase
{
    public function testAddRemoveOne(): void
    {
        /** @var ?string $argCallbackCalledWith */
        $argCallbackCalledWith = null;
        $callback = function (string $arg) use (&$argCallbackCalledWith): void {
            self::assertNull($argCallbackCalledWith);
            $argCallbackCalledWith = $arg;
        };

        /**
         * @var ObserverSet<string> $observers
         * @noinspection PhpRedundantVariableDocTypeInspection
         */
        $observers = new ObserverSet();

        $observers->callCallbacks('testArg1');
        self::assertNull($argCallbackCalledWith);

        $observers->add($callback);
        $observers->callCallbacks('testArg2');
        self::assertSame('testArg2', $argCallbackCalledWith); // @phpstan-ignore-line
        $argCallbackCalledWith = null;

        $observers->remove($callback);
        $observers->callCallbacks('testArg3');
        self::assertNull($argCallbackCalledWith);
    }

    public function testAddRemoveTwo(): void
    {
        /** @var ?float $argCallback1CalledWith */
        $argCallback1CalledWith = null;
        $callback1 = function (float $arg) use (&$argCallback1CalledWith): void {
            self::assertNull($argCallback1CalledWith);
            $argCallback1CalledWith = $arg;
        };
        /** @var ?float $argCallback2CalledWith */
        $argCallback2CalledWith = null;
        $callback2 = function (float $arg) use (&$argCallback2CalledWith): void {
            self::assertNull($argCallback2CalledWith);
            $argCallback2CalledWith = $arg;
        };

        /**
         * @var ObserverSet<float> $observers
         * @noinspection PhpRedundantVariableDocTypeInspection
         */
        $observers = new ObserverSet();

        $observers->add($callback1);
        $observers->callCallbacks(1.1);
        self::assertSame(1.1, $argCallback1CalledWith);
        $argCallback1CalledWith = null;

        $observers->add($callback2);
        $observers->callCallbacks(22.22);
        self::assertSame(22.22, $argCallback1CalledWith); // @phpstan-ignore-line
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $argCallback1CalledWith = null;
        self::assertSame(22.22, $argCallback2CalledWith);
        $argCallback2CalledWith = null;

        $observers->remove($callback1);
        $observers->callCallbacks(333.333);
        self::assertNull($argCallback1CalledWith);
        self::assertSame(333.333, $argCallback2CalledWith); // @phpstan-ignore-line
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $argCallback2CalledWith = null;

        $observers->remove($callback2);
        $observers->callCallbacks(4444.4444);
        self::assertNull($argCallback1CalledWith);
        self::assertNull($argCallback2CalledWith);
    }
}
