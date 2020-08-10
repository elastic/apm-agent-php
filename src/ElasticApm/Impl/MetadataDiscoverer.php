<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Util\TextUtil;
use Elastic\Apm\Metadata;
use Elastic\Apm\ServiceData;
use Elastic\Apm\Impl\Config\Snapshot as ConfigSnapshot;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class MetadataDiscoverer
{

    public static function discover(ConfigSnapshot $config): Metadata
    {
        $result = new Metadata();

        self::discoverServiceData($config, $result->service());

        return $result;
    }

    private static function discoverServiceData(ConfigSnapshot $config, ServiceData $serviceData): void
    {
        $serviceData->setName(is_null($config->serviceName())
                                  ? ServiceData::DEFAULT_SERVICE_NAME
                                  : MetadataDiscoverer::adaptServiceName($config->serviceName()));

        if (!is_null($config->serviceVersion())) {
            $serviceData->setVersion(TextUtil::limitKeywordString($config->serviceVersion()));
        }
    }

    public static function adaptServiceName(string $configuredName): string
    {
        if (empty($configuredName)) {
            return ServiceData::DEFAULT_SERVICE_NAME;
        }

        $charsAdaptedName = preg_replace('/[^a-zA-Z0-9 _\-]/', '_', $configuredName);
        return is_null($charsAdaptedName)
            ? ServiceData::DEFAULT_SERVICE_NAME
            : TextUtil::limitKeywordString($charsAdaptedName);
    }
}
