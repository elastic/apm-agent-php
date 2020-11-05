<?php

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\Util;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;

abstract class AgentConfigSetter implements LoggableInterface
{
    use LoggableTrait;

    /** @var array<string, string> */
    public $optionNameToValue = [];

    abstract public function appCodePhpCmd(): string;

    /**
     * @return array<string, string>
     */
    abstract public function additionalEnvVars(): array;

    abstract public function tearDown(): void;

    /**
     * @param string $optName
     * @param string $optVal
     *
     * @return $this
     */
    public function set(string $optName, string $optVal): self
    {
        $this->optionNameToValue[$optName] = $optVal;

        return $this;
    }

    protected static function buildAppCodePhpCmd(?string $appCodePhpIni): string
    {
        $result = AmbientContext::testConfig()->appCodePhpExe ?? 'php';
        if (!is_null($appCodePhpIni)) {
            $result .= ' -c ' . $appCodePhpIni;
        }
        return $result;
    }
}
