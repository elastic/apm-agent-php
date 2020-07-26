<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests;

use Elastic\Apm\Impl\MetadataDiscoverer;
use Elastic\Apm\Tests\ComponentTests\Util\ComponentTestCaseBase;
use Elastic\Apm\Tests\ComponentTests\Util\DataFromAgent;
use Elastic\Apm\Tests\ComponentTests\Util\TestEnvBase;
use Elastic\Apm\Tests\ComponentTests\Util\TestProperties;

final class MetadataTest extends ComponentTestCaseBase
{
    public static function appCodeEmpty(): void
    {
    }

    public function testDefaultServiceName(): void
    {
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgentEx(
            (new TestProperties([__CLASS__, 'appCodeEmpty'])),
            function (DataFromAgent $dataFromAgent): void {
                TestEnvBase::verifyServiceName(MetadataDiscoverer::DEFAULT_SERVICE_NAME, $dataFromAgent);
            }
        );
    }

    public function testCustomServiceName(): void
    {
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgentEx(
            (new TestProperties([__CLASS__, 'appCodeEmpty']))->withConfiguredServiceName('custom service name'),
            function (DataFromAgent $dataFromAgent): void {
                TestEnvBase::verifyServiceName('custom service name', $dataFromAgent);
            }
        );
    }

    public function testInvalidServiceName(): void
    {
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgentEx(
            (new TestProperties([__CLASS__, 'appCodeEmpty']))->withConfiguredServiceName(
                '1CUSTOM -@- sErvIcE -+- NaMe9'
            ),
            function (DataFromAgent $dataFromAgent): void {
                TestEnvBase::verifyServiceName('1CUSTOM -_- sErvIcE -_- NaMe9', $dataFromAgent);
            }
        );
    }
}
