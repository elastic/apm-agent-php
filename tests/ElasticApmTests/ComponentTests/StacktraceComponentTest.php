<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests;

use Elastic\Apm\Tests\ComponentTests\Util\ComponentTestCaseBase;
use Elastic\Apm\Tests\ComponentTests\Util\DataFromAgent;
use Elastic\Apm\Tests\UnitTests\StacktraceTestSharedCode;

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
            [__CLASS__, 'appCodeForTestAllSpanCreatingApis'],
            function (DataFromAgent $dataFromAgent) use ($expectedData, $createSpanApis): void {
                StacktraceTestSharedCode::assertPartImpl(
                    count($createSpanApis),
                    $expectedData,
                    $dataFromAgent->idToSpan
                );
            }
        );
    }
}
