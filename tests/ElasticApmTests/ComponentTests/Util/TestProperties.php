<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\ObjectToStringBuilder;

final class TestProperties
{
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

    /** @var string|null */
    public $transactionName = null;

    /** @var string|null */
    public $transactionType = null;

    public function __construct(callable $appCodeClassMethod)
    {
        assert(is_array($appCodeClassMethod));
        $this->appCodeClass = $appCodeClassMethod[0];
        $this->appCodeMethod = $appCodeClassMethod[1];
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

    public function buildUri(): string
    {
        return $this->uriPath;
    }

    public function __toString(): string
    {
        $builder = new ObjectToStringBuilder(DbgUtil::fqToShortClassName(get_called_class()));
        $builder->add('appCodeClass', $this->appCodeClass);
        $builder->add('appCodeMethod', $this->appCodeMethod);
        $builder->add('httpMethod', $this->httpMethod);
        return $builder->build();
    }
}
