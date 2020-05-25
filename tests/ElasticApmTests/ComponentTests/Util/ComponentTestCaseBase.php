<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Closure;
use Elastic\Apm\Impl\Clock;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Tests\Util\TestCaseBase;
use Elastic\Apm\Tests\Util\TestLogCategory;
use RuntimeException;

class ComponentTestCaseBase extends TestCaseBase
{
    /** @var Logger */
    private $logger;

    /** @var TestEnvBase */
    private $testEnv;

    public function __construct()
    {
        AmbientContext::init(/* dbgProcessName */ 'Component tests');
        parent::__construct();
        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            TestLogCategory::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        );

        $this->testEnv = $this->selectTestEnv();
    }

    public function tearDown(): void
    {
        $this->testEnv->shutdown();
    }

    /**
     * @param callable $appCodeClassMethod
     * @param Closure  $verifyFunc
     *
     * @return void
     *
     * @phpstan-param Closure(DataFromAgent): void $verifyFunc
     */
    protected function sendRequestToInstrumentedAppAndVerifyDataFromAgent(
        callable $appCodeClassMethod,
        Closure $verifyFunc
    ): void {
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgentEx(
            new TestProperties($appCodeClassMethod),
            $verifyFunc
        );
    }

    /**
     * @param TestProperties $testProperties
     * @param Closure        $verifyFunc
     *
     * @return void
     *
     * @phpstan-param Closure(DataFromAgent): void $verifyFunc
     */
    protected function sendRequestToInstrumentedAppAndVerifyDataFromAgentEx(
        TestProperties $testProperties,
        Closure $verifyFunc
    ): void {
        $this->testEnv->sendRequestToInstrumentedAppAndVerifyDataFromAgent($testProperties, $verifyFunc);
    }

    private function selectTestEnv(): TestEnvBase
    {
        switch (AmbientContext::config()->appCodeHostKind()) {
            case AppCodeHostKind::CLI_SCRIPT:
                return new CliScriptTestEnv();

            case AppCodeHostKind::CLI_BUILTIN_HTTP_SERVER:
                return new BuiltinHttpServerTestEnv();

            case AppCodeHostKind::EXTERNAL_HTTP_SERVER:
                return new ExternalHttpServerTestEnv();
        }

        throw new RuntimeException('This point in the code should not be reached');
    }
}
