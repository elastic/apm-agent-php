<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

final class AgentConfigSetterIni extends AgentConfigSetterBase
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
