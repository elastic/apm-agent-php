<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

final class ConfigSetterNoop extends ConfigSetterBase
{
    public function appCodePhpCmd(): string
    {
        return self::buildAppCodePhpCmd(AmbientContext::config()->appCodePhpIni());
    }

    public function additionalEnvVars(): array
    {
        return [];
    }

    public function tearDown(): void
    {
    }
}
