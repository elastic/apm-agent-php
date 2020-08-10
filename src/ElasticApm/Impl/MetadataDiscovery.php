<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Metadata;
use Elastic\Apm\NameVersionData;
use Elastic\Apm\ProcessData;
use Elastic\Apm\ServiceData;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class MetadataDiscovery
{

    public static function discover(): Metadata
    {
        $metadata = new Metadata();
        self::discoverServiceData($metadata->service());
        return $metadata;
    }

    private static function discoverServiceData(ServiceData $serviceData): void
    {
        $serviceData->setName(ServiceData::DEFAULT_SERVICE_NAME);

        $serviceData->agent()->setName(ServiceData::DEFAULT_AGENT_NAME);
        $serviceData->agent()->setVersion(ElasticApm::VERSION);

        $serviceData->language()->setName(ServiceData::DEFAULT_LANGUAGE_NAME);
        $serviceData->language()->setVersion(PHP_VERSION);

        $serviceData->runtime()->setName($serviceData->language()->getName());
        $serviceData->runtime()->setVersion($serviceData->language()->getVersion());
    }
}
