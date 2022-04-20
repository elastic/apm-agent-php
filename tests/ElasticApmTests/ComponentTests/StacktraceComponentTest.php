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

namespace ElasticApmTests\ComponentTests;

use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Util\TextUtil;
use Elastic\Apm\SpanInterface;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\DataFromAgent;
use ElasticApmTests\ComponentTests\Util\TestProperties;
use ElasticApmTests\ComponentTests\Util\TopLevelCodeId;
use ElasticApmTests\TestsSharedCode\StacktraceTestSharedCode;

class StacktraceComponentTest extends ComponentTestCaseBase
{
    /**
     * @return array<string, mixed>
     */
    private static function sharedCodeForTestAllSpanCreatingApis(): array
    {
        /** @var array<string, mixed> */
        $expectedData = [];
        $createSpanApis = StacktraceTestSharedCode::allSpanCreatingApis(/* ref */ $expectedData);

        foreach ($createSpanApis as $createSpan) {
            (new StacktraceTestSharedCode())->actPartImpl($createSpan, /* ref */ $expectedData);
        }

        return ['expectedData' => $expectedData, 'createSpanApis' => $createSpanApis];
    }

    public static function appCodeForTestAllSpanCreatingApis(): void
    {
        self::sharedCodeForTestAllSpanCreatingApis();
    }

    public function testAllSpanCreatingApis(): void
    {
        $sharedCodeResult = self::sharedCodeForTestAllSpanCreatingApis();
        /** @var array<string, mixed> */
        $expectedData = $sharedCodeResult['expectedData'];
        /**
         * @var array<callable>
         * @phpstan-var array<callable(): void>
         */
        $createSpanApis = $sharedCodeResult['createSpanApis'];

        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            (new TestProperties())->withRoutedAppCode([__CLASS__, 'appCodeForTestAllSpanCreatingApis']),
            function (DataFromAgent $dataFromAgent) use ($expectedData, $createSpanApis): void {
                StacktraceTestSharedCode::assertPartImpl(
                    count($createSpanApis),
                    $expectedData,
                    $dataFromAgent->parsed()->idToSpan
                );
            }
        );
    }

    public function testTopLevelTransactionBeginCurrentSpanApi(): void
    {
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            (new TestProperties())->withTopLevelAppCode(TopLevelCodeId::SPAN_BEGIN_END),
            function (DataFromAgent $dataFromAgent): void {
                $span = $dataFromAgent->singleSpan();
                self::assertSame('top_level_code_span_name', $span->name);
                self::assertSame('top_level_code_span_type', $span->type);
                $actualStacktrace = $span->stacktrace;
                self::assertNotNull($actualStacktrace);
                self::assertCount(1, $actualStacktrace, LoggableToString::convert($actualStacktrace));
                /** @var string */
                $expectedFileName = self::getLabel($span, 'top_level_code_span_end_file_name');
                self::assertTrue(TextUtil::isSuffixOf('.php', $expectedFileName), $expectedFileName);
                self::assertSame($expectedFileName, $actualStacktrace[0]->filename);
                self::assertSame(
                    self::getLabel($span, 'top_level_code_span_end_line_number'),
                    $actualStacktrace[0]->lineno
                );
                self::assertSame(
                    StacktraceTestSharedCode::buildMethodName(SpanInterface::class, 'end'),
                    $actualStacktrace[0]->function
                );
            }
        );
    }
}
