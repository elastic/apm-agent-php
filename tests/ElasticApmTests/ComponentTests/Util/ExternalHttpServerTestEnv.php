<?php

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\Util;

final class ExternalHttpServerTestEnv extends HttpServerTestEnvBase
{
    protected function ensureAppCodeHostServerRunning(TestProperties $testProperties): void
    {
    }
}
