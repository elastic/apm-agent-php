<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\ObjectToStringBuilder;

final class TestProperties
{
    /** @var array<string, mixed>|null */
    public $appCodeArgs;

    /** @var string */
    public $appCodeClass;

    /** @var string */
    public $appCodeMethod;

    /** @var string */
    public $httpMethod = 'GET';

    /** @var string */
    public $uriPath = '/';

    /** @var int */
    public $expectedStatusCode = HttpConsts::STATUS_OK;

    /** @var ?string */
    public $transactionName = null;

    /** @var ?string */
    public $transactionType = null;

    /** @var ConfigSetterBase */
    public $configSetter;

    /** @var array<?string> */
    public $configuredOptions = [];

    /**
     * TestProperties constructor.
     *
     * @param callable                  $appCodeClassMethod
     * @param array<string, mixed>|null $appCodeArgs
     */
    public function __construct(callable $appCodeClassMethod, ?array $appCodeArgs = null)
    {
        assert(is_array($appCodeClassMethod));
        $this->appCodeClass = $appCodeClassMethod[0];
        $this->appCodeMethod = $appCodeClassMethod[1];

        $this->appCodeArgs = $appCodeArgs;

        $this->configSetter = new ConfigSetterNoop();
    }

    public function withHttpMethod(string $httpMethod): self
    {
        $this->httpMethod = $httpMethod;
        return $this;
    }

    public function withUriPath(string $uriPath): self
    {
        $this->uriPath = $uriPath;
        return $this;
    }

    public function withExpectedStatusCode(int $expectedStatusCode): self
    {
        $this->expectedStatusCode = $expectedStatusCode;
        return $this;
    }

    public function withTransactionName(string $transactionName): self
    {
        $this->transactionName = $transactionName;
        return $this;
    }

    public function withTransactionType(string $transactionType): self
    {
        $this->transactionType = $transactionType;
        return $this;
    }

    public function withAgentOption(string $optName, string $optVal): self
    {
        $this->configuredOptions[$optName] = $optVal;
        return $this;
    }

    public function getConfiguredAgentOption(string $optName): ?string
    {
        return ArrayUtil::getValueIfKeyExistsElse($optName, $this->configuredOptions, null);
    }

    public function withConfigSetter(ConfigSetterBase $configSetter): ConfigSetterBase
    {
        $this->configSetter = $configSetter;
        $configSetter->setParent($this);
        return $configSetter;
    }

    public function tearDown(): void
    {
        $this->configSetter->tearDown();
    }

    public function __toString(): string
    {
        $builder = new ObjectToStringBuilder(DbgUtil::fqToShortClassName(get_called_class()));
        $builder->add('appCodeClass', $this->appCodeClass);
        $builder->add('appCodeMethod', $this->appCodeMethod);
        $builder->add('httpMethod', $this->httpMethod);
        $builder->add('configSetter', $this->configSetter);
        $builder->add('configuredOptions', $this->configuredOptionsToString());
        return $builder->build();
    }

    private function configuredOptionsToString(): string
    {
        $builder = new ObjectToStringBuilder();
        foreach ($this->configuredOptions as $optName => $optVal) {
            $builder->add($optName, $optVal);
        }
        return $builder->build();
    }
}
