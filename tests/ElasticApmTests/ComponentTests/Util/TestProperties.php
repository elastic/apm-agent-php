<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

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

    /** @var ?string */
    public $configuredApiKey = null;

    /** @var ?string */
    public $configuredEnvironment = null;

    /** @var ?string */
    public $configuredSecretToken = null;

    /** @var ?string */
    public $configuredServiceName = null;

    /** @var ?string */
    public $configuredServiceVersion = null;

    /** @var ?string */
    public $configuredTransactionSampleRate = null;

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

    public function withConfig(ConfigSetterBase $configSetter): ConfigSetterBase
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
        $builder->add('configuredApiKey', $this->configuredApiKey);
        $builder->add('configuredEnvironment', $this->configuredEnvironment);
        $builder->add('configuredSecretToken', $this->configuredSecretToken);
        $builder->add('configuredServiceName', $this->configuredServiceName);
        $builder->add('configuredServiceVersion', $this->configuredServiceVersion);
        $builder->add('configuredTransactionSampleRate', $this->configuredTransactionSampleRate);
        return $builder->build();
    }
}
