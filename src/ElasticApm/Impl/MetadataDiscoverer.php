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

    public static function discoverMetadata(ConfigSnapshot $config): MetadataInterface
    {
        return (new class extends Metadata {
            public static function discoverImpl(ConfigSnapshot $config): MetadataInterface
            {
                $result = new Metadata();

                $result->process = MetadataDiscoverer::discoverProcessData();
                $result->service = MetadataDiscoverer::discoverServiceData($config);

                return $result;
            }
        })->discoverImpl($config);
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

    public static function discoverServiceData(ConfigSnapshot $config): ServiceDataInterface
    {
        return (new class extends ServiceData {
            public static function discoverImpl(ConfigSnapshot $config): ServiceDataInterface
            {
                $result = new ServiceData();

                $result->name = is_null($config->serviceName())
                    ? MetadataDiscoverer::DEFAULT_SERVICE_NAME
                    : MetadataDiscoverer::adaptServiceName($config->serviceName());
                // $result->version = ???;

                $result->agent = new NameVersionData(MetadataDiscoverer::AGENT_NAME, ElasticApm::VERSION);
                $result->language = new NameVersionData(MetadataDiscoverer::LANGUAGE_NAME, PHP_VERSION);
                $result->runtime = $result->language;

                return $result;
            }
        })->discoverImpl($config);
    }

    public static function discoverProcessData(): ProcessDataInterface
    {
        return (new class extends ProcessData {
            public static function discoverImpl(): ProcessDataInterface
            {
                $result = new ProcessData();

                $result->pid = getmypid();

                return $result;
            }
        })->discoverImpl();
    }
}
