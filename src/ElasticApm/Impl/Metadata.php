<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Util\ObjectToStringBuilder;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class Metadata extends EventData implements MetadataInterface
{
    /** @var ProcessDataInterface */
    protected $process;

    /** @var ServiceDataInterface */
    protected $service;

    /** @inheritDoc */
    public function process(): ProcessDataInterface
    {
        return $this->process;
    }

    /** @inheritDoc */
    public function service(): ServiceDataInterface
    {
        return $this->service;
    }

    /**
     * @param mixed $propValue
     *
     * @return mixed
     */
    protected static function convertPropertyValueToData($propValue)
    {
        if ($propValue instanceof ServiceDataInterface) {
            return ServiceData::convertToData($propValue);
        }

        if ($propValue instanceof ProcessDataInterface) {
            return ProcessData::convertToData($propValue);
        }

        return parent::convertPropertyValueToData($propValue);
    }

    public static function dataToString(MetadataInterface $data, string $type): string
    {
        $builder = new ObjectToStringBuilder($type);
        $builder->add('process', $data->process());
        $builder->add('service', $data->service());
        return $builder->build();
    }

    public function __toString(): string
    {
        return self::dataToString($this, 'Metadata');
    }
}
