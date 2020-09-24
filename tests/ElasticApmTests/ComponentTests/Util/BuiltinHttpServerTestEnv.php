<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Tests\Util\TestLogCategory;

final class BuiltinHttpServerTestEnv extends HttpServerTestEnvBase
{
    private const APP_CODE_HOST_ROUTER_SCRIPT = 'routeToCliBuiltinHttpServerAppCodeHost.php';

    /** @var Logger */
    private $logger;

    public function __construct()
    {
        parent::__construct();

        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            TestLogCategory::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);
    }

    protected function ensureAppCodeHostServerRunning(TestProperties $testProperties): void
    {
        $this->ensureHttpServerIsRunning(
            $this->appCodeHostServerPort /* <- ref */,
            $this->appCodeHostServerId /* <- ref */,
            DbgUtil::fqToShortClassName(BuiltinHttpServerAppCodeHost::class) /* <- dbgServerDesc */,
            /* cmdLineGenFunc: */
            function (int $port) use ($testProperties) {
                return $testProperties->configSetter->appCodePhpCmd()
                       . " -S localhost:$port"
                       . ' "' . __DIR__ . DIRECTORY_SEPARATOR . self::APP_CODE_HOST_ROUTER_SCRIPT . '"';
            },
            $testProperties->configSetter->additionalEnvVars()
        );
    }
}
