<?php

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\Util;

final class AgentConfigSetterIni extends AgentConfigSetter
{
    public function appCodePhpCmd(): string
    {
        return self::buildAppCodePhpCmd(AmbientContext::testConfig()->appCodePhpIni);
    }

    public function additionalEnvVars(): array
    {
        return [];
    }

    public function tearDown(): void
    {
    }
}
