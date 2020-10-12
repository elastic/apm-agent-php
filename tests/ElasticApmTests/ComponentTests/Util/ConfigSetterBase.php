<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Util\DbgUtil;

abstract class ConfigSetterBase
{
    /** @var TestProperties */
    protected $parent;

    abstract public function appCodePhpCmd(): string;

    /**
     * @return array<string, string>
     */
    abstract public function additionalEnvVars(): array;

    abstract public function tearDown(): void;

    public function setParent(TestProperties $parent): void
    {
        $this->parent = $parent;
    }

    public function getParent(): TestProperties
    {
        return $this->parent;
    }

    public function apiKey(?string $configuredApiKey): self
    {
        $this->parent->configuredApiKey = $configuredApiKey;
        return $this;
    }

    public function environment(?string $configuredEnvironment): self
    {
        $this->parent->configuredEnvironment = $configuredEnvironment;
        return $this;
    }

    public function secretToken(?string $configuredSecretToken): self
    {
        $this->parent->configuredSecretToken = $configuredSecretToken;
        return $this;
    }

    public function serviceName(?string $configuredServiceName): self
    {
        $this->parent->configuredServiceName = $configuredServiceName;
        return $this;
    }

    public function serviceVersion(?string $configuredServiceVersion): self
    {
        $this->parent->configuredServiceVersion = $configuredServiceVersion;
        return $this;
    }

    public function transactionSampleRate(?string $configuredTransactionSampleRate): self
    {
        $this->parent->configuredTransactionSampleRate = $configuredTransactionSampleRate;
        return $this;
    }

    protected static function buildAppCodePhpCmd(?string $appCodePhpIni): string
    {
        $result = AmbientContext::config()->appCodePhpExe() ?? 'php';
        if (!is_null($appCodePhpIni)) {
            $result .= ' -c ' . $appCodePhpIni;
        }
        return $result;
    }

    public function __toString(): string
    {
        return DbgUtil::fqToShortClassName(get_class($this));
    }
}
