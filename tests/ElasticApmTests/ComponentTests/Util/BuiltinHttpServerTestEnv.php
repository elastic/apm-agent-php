<?php

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\Util;

use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\DbgUtil;
use ElasticApmTests\Util\LogCategoryForTests;

final class BuiltinHttpServerTestEnv extends HttpServerTestEnvBase
{
    private const APP_CODE_HOST_ROUTER_SCRIPT = 'routeToCliBuiltinHttpServerAppCodeHost.php';

    /** @var Logger */
    private $logger;

    public function __construct()
    {
        parent::__construct();

        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
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
                return $testProperties->agentConfigSetter->appCodePhpCmd()
                       . " -S localhost:$port"
                       . ' "' . __DIR__ . DIRECTORY_SEPARATOR . self::APP_CODE_HOST_ROUTER_SCRIPT . '"';
            },
            false /* $keepElasticApmEnvVars */,
            $testProperties->agentConfigSetter->additionalEnvVars()
        );
    }
}
