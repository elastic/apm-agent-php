<?php

declare(strict_types=1);

namespace Elastic\Apm;

use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\ObjectToStringBuilder;
use Elastic\Apm\Impl\Util\SerializationUtil;
use JsonSerializable;

class Metadata implements JsonSerializable
{
    /** @var ProcessData */
    private $process;

    /** @var ServiceData */
    private $service;

    public function __construct()
    {
        $this->process = new ProcessData();
        $this->service = new ServiceData();
    }

    /**
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/metadata.json#L22
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/process.json
     */
    public function process(): ProcessData
    {
        return $this->process;
    }

    /**
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/metadata.json#L7
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json
     */
    public function service(): ServiceData
    {
        return $this->service;
    }

    /**
     * @return array<string, mixed>
     *
     * Called by json_encode
     * @noinspection PhpUnused
     */
    public function jsonSerialize(): array
    {
        return SerializationUtil::buildJsonSerializeResult(
            [
                'process' => $this->process,
                'service' => $this->service,
            ]
        );
    }

    public function __toString(): string
    {
        $builder = new ObjectToStringBuilder(DbgUtil::fqToShortClassName(get_class()));
        $builder->add('process', $this->process());
        $builder->add('service', $this->service());
        return $builder->build();
    }
}
