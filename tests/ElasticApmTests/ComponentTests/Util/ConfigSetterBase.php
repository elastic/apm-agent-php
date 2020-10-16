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

    public function getParent(): TestProperties
    {
        return $this->parent;
    }

    public function setParent(TestProperties $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * @param string $optName
     * @param mixed  $optVal
     *
     * @return $this
     */
    public function setOption(string $optName, $optVal): self
    {
        $this->parent->configuredOptions[$optName] = $optVal;
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
