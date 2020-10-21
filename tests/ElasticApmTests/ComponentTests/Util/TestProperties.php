<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\ObjectToStringBuilder;
use PHPUnit\Framework\TestCase;

final class TestProperties
{
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

    /** @var AgentConfigSetterBase */
    public $agentConfigSetter;

    /** @var SharedDataPerRequest */
    public $sharedDataPerRequest;

    public function __construct()
    {
        $this->agentConfigSetter = new AgentConfigSetterEnvVars();
        $this->sharedDataPerRequest = new SharedDataPerRequest();
    }

    public function withRoutedAppCode(callable $appCodeClassMethod): self
    {
        TestCase::assertTrue(is_null($this->sharedDataPerRequest->appTopLevelCodeId));

        TestCase::assertTrue(is_callable($appCodeClassMethod));
        TestCase::assertTrue(is_array($appCodeClassMethod));
        /** @noinspection PhpParamsInspection */
        TestCase::assertCount(2, $appCodeClassMethod);

        $this->sharedDataPerRequest->appCodeClass = $appCodeClassMethod[0];
        $this->sharedDataPerRequest->appCodeMethod = $appCodeClassMethod[1];

        return $this;
    }

    public function withTopLevelAppCode(string $topLevelCodeId): self
    {
        TestCase::assertTrue(is_null($this->sharedDataPerRequest->appCodeClass));
        TestCase::assertTrue(is_null($this->sharedDataPerRequest->appCodeMethod));

        $this->sharedDataPerRequest->appTopLevelCodeId = $topLevelCodeId;

        return $this;
    }

    /**
     * @param array<string, mixed> $appCodeArgs
     *
     * @return TestProperties
     */
    public function withAppArgs(array $appCodeArgs): self
    {
        $this->sharedDataPerRequest->appCodeArguments = $appCodeArgs;

        return $this;
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

    public function getConfiguredAgentOption(string $optName): ?string
    {
        return ArrayUtil::getValueIfKeyExistsElse($optName, $this->agentConfigSetter->optionNameToValue, null);
    }

    public function withAgentConfig(AgentConfigSetterBase $configSetter): self
    {
        $this->agentConfigSetter = $configSetter;
        return $this;
    }

    public function tearDown(): void
    {
        $this->agentConfigSetter->tearDown();
    }

    public function __toString(): string
    {
        return ObjectToStringBuilder::buildUsingAllProperties($this);
    }
}
