<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Config\Snapshot as ConfigSnapshot;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class MetadataDiscoverer
{
    public const AGENT_NAME = 'php';
    public const LANGUAGE_NAME = 'PHP';
    public const DEFAULT_SERVICE_NAME = 'Unnamed PHP service';

    public static function discoverMetadata(ConfigSnapshot $config): Metadata
    {
        $result = new Metadata();

        $result->process = MetadataDiscoverer::discoverProcessData();
        $result->service = MetadataDiscoverer::discoverServiceData($config);

        return $result;
    }

    public static function adaptServiceName(string $configuredName): string
    {
        if (empty($configuredName)) {
            return self::DEFAULT_SERVICE_NAME;
        }

        $charsAdaptedName = preg_replace('/[^a-zA-Z0-9 _\-]/', '_', $configuredName);
        return is_null($charsAdaptedName)
            ? MetadataDiscoverer::DEFAULT_SERVICE_NAME
            : Tracer::limitKeywordString($charsAdaptedName);
    }

    public static function discoverServiceData(ConfigSnapshot $config): ServiceData
    {
        $result = new ServiceData();

        if (!is_null($config->environment())) {
            $result->environment = Tracer::limitKeywordString($config->environment());
        }

        $result->name = is_null($config->serviceName())
            ? MetadataDiscoverer::DEFAULT_SERVICE_NAME
            : MetadataDiscoverer::adaptServiceName($config->serviceName());

        if (!is_null($config->serviceVersion())) {
            $result->version = Tracer::limitKeywordString($config->serviceVersion());
        }

        $result->agent = new ServiceAgentData();
        $result->agent->name = self::AGENT_NAME;
        $result->agent->version = ElasticApm::VERSION;

        $result->language = self::buildNameVersionData(MetadataDiscoverer::LANGUAGE_NAME, PHP_VERSION);

        $result->runtime = $result->language;

        return $result;
    }

    public static function buildNameVersionData(?string $name, ?string $version): NameVersionData
    {
        $result = new NameVersionData();

        $result->name = $name;
        $result->version = $version;

        return $result;
    }

    public static function discoverProcessData(): ProcessData
    {
        $result = new ProcessData();

        $result->pid = getmypid();

        return $result;
    }
}
