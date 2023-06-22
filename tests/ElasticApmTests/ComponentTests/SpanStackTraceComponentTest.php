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
use ElasticApmTests\ComponentTests\Util\AppCodeHostParams;
use ElasticApmTests\ComponentTests\Util\AppCodeTarget;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\ExpectedEventCounts;
use ElasticApmTests\ComponentTests\Util\TopLevelCodeId;
use ElasticApmTests\TestsSharedCode\SpanStackTraceTestSharedCode;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
class SpanStackTraceComponentTest extends ComponentTestCaseBase
{
    /**
     * Tests in this class specifiy expected spans individually
     * so Span Compression feature should be disabled.
     *
     * @inheritDoc
     */
    protected function isSpanCompressionCompatible(): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private static function sharedCodeForTestAllSpanCreatingApis(): array
    {
        /** @var array<string, mixed> $expectedData */
        $expectedData = [];
        $createSpanApis = SpanStackTraceTestSharedCode::allSpanCreatingApis(/* ref */ $expectedData);

        foreach ($createSpanApis as $createSpan) {
            (new SpanStackTraceTestSharedCode())->actPartImpl($createSpan, /* ref */ $expectedData);
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
        /** @var array<string, mixed> $expectedData */
        $expectedData = $sharedCodeResult['expectedData'];
        /** @var array<callable(): void> $createSpanApis */
        $createSpanApis = $sharedCodeResult['createSpanApis'];

        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams): void {
                self::disableTimingDependentFeatures($appCodeParams);
            }
        );
        $appCodeHost->sendRequest(AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestAllSpanCreatingApis']));
        $expectedMinSpansCount = count($createSpanApis);
        $dataFromAgent = $testCaseHandle->waitForDataFromAgent(
            (new ExpectedEventCounts())->transactions(1)->spans($expectedMinSpansCount, PHP_INT_MAX)
        );
        SpanStackTraceTestSharedCode::assertPartImpl($expectedMinSpansCount, $expectedData, $dataFromAgent->idToSpan);
    }

    public function testTopLevelTransactionBeginCurrentSpanApi(): void
    {
        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams): void {
                self::disableTimingDependentFeatures($appCodeParams);
            }
        );
        $appCodeHost->sendRequest(AppCodeTarget::asTopLevel(TopLevelCodeId::SPAN_BEGIN_END));
        $dataFromAgent = $testCaseHandle->waitForDataFromAgent(
            (new ExpectedEventCounts())->transactions(1)->spans(1)
        );
        $span = $dataFromAgent->singleSpan();
        self::assertSame('top_level_code_span_name', $span->name);
        self::assertSame('top_level_code_span_type', $span->type);
        $actualStacktrace = $span->stackTrace;
        self::assertNotNull($actualStacktrace);
        self::assertCount(1, $actualStacktrace, LoggableToString::convert($actualStacktrace));
        /** @var string $expectedFileName */
        $expectedFileName = self::getLabel($span, 'top_level_code_span_end_file_name');
        self::assertTrue(TextUtil::isSuffixOf('.php', $expectedFileName), $expectedFileName);
        self::assertSame($expectedFileName, $actualStacktrace[0]->filename);
        self::assertSame(self::getLabel($span, 'top_level_code_span_end_line_number'), $actualStacktrace[0]->lineno);
        self::assertNull($actualStacktrace[0]->function);
    }
}
