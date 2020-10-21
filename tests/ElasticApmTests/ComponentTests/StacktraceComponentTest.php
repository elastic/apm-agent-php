<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests;

use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\TextUtil;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\Tests\ComponentTests\Util\ComponentTestCaseBase;
use Elastic\Apm\Tests\ComponentTests\Util\DataFromAgent;
use Elastic\Apm\Tests\ComponentTests\Util\TestProperties;
use Elastic\Apm\Tests\ComponentTests\Util\TopLevelCodeId;
use Elastic\Apm\Tests\TestsSharedCode\StacktraceTestSharedCode;

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
                    $dataFromAgent->idToSpan
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
                self::assertSame('top_level_code_span_name', $span->getName());
                self::assertSame('top_level_code_span_type', $span->getType());
                $actualStacktrace = $span->getStacktrace();
                self::assertNotNull($actualStacktrace);
                self::assertCount(1, $actualStacktrace, DbgUtil::formatValue($actualStacktrace));
                /** @var string */
                $expectedFileName = $span->getLabels()['top_level_code_span_end_file_name'];
                self::assertTrue(TextUtil::isSuffixOf('.php', $expectedFileName), $expectedFileName);
                self::assertSame($expectedFileName, $actualStacktrace[0]->filename);
                self::assertSame(
                    $span->getLabels()['top_level_code_span_end_line_number'],
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
