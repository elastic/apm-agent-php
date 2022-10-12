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

namespace ElasticApmTests\UnitTests\ConfigTests;

use Elastic\Apm\Impl\Config\DevInternalSubOptionNames;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Config\SnapshotDevInternal;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\Util\TextUtil;
use ElasticApmTests\Util\TestCaseBase;
use ReflectionClass;

class DevInternalOptionParserTest extends TestCaseBase
{
    /**
     * @return string[]
     */
    private static function devInternalSubOptionNames(): array
    {
        return [
            DevInternalSubOptionNames::DROP_EVENT_AFTER_END,
            DevInternalSubOptionNames::DROP_EVENTS_BEFORE_SEND_C_CODE,
            DevInternalSubOptionNames::GC_COLLECT_CYCLES_AFTER_EVERY_TRANSACTION,
            DevInternalSubOptionNames::GC_MEM_CACHES_AFTER_EVERY_TRANSACTION,
        ];
    }

    public function testSubOptionNamesMatchSnapshotProperties(): void
    {
        $reflectionClass = new ReflectionClass(SnapshotDevInternal::class);
        $snapshotProperties = [];
        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $propName = $reflectionProperty->name;
            $subOptName = TextUtil::camelToSnakeCase($propName);
            self::assertContains($subOptName, self::devInternalSubOptionNames());
            $snapshotProperties[] = $subOptName;
        }

        self::assertEqualAsSets(self::devInternalSubOptionNames(), $snapshotProperties);
    }

    public function testParsing(): void
    {
        foreach (self::devInternalSubOptionNames() as $subOptToSet) {
            $tracer = self::buildTracerForTests()->withConfig(OptionNames::DEV_INTERNAL, $subOptToSet)->build();
            $this->assertInstanceOf(Tracer::class, $tracer);
            foreach (self::devInternalSubOptionNames() as $subOptToCheck) {
                $subOptToCheckAsPropName = TextUtil::snakeToCamelCase($subOptToCheck);
                $expectedVal = $subOptToCheck === $subOptToSet;
                $actualVal = $tracer->getConfig()->devInternal()->$subOptToCheckAsPropName();
                self::assertSame(
                    $expectedVal,
                    $actualVal,
                    LoggableToString::convert(
                        [
                            'expectedVal'             => $expectedVal,
                            'actualVal'               => $actualVal,
                            'subOptToSet'             => $subOptToSet,
                            'subOptToCheck'           => $subOptToCheck,
                            'subOptToCheckAsPropName' => $subOptToCheckAsPropName,
                        ]
                    )
                );
            }
        }
    }
}
