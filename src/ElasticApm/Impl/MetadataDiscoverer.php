<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\ElasticApm;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class MetadataDiscoverer
{
    public static function discoverMetadata(): MetadataInterface
    {
        return (new class extends Metadata {
            public static function discoverImpl(): MetadataInterface
            {
                $result = new Metadata();

                $result->process = MetadataDiscoverer::discoverProcessData();
                $result->service = MetadataDiscoverer::discoverServiceData();

                return $result;
            }
        })->discoverImpl();
    }

    public static function discoverServiceData(): ServiceDataInterface
    {
        return (new class extends ServiceData {
            public static function discoverImpl(): ServiceDataInterface
            {
                $result = new ServiceData();

                // TODO: Sergey Kleyman: Implement: Getting service name and version from configuration
                $result->name = 'Unknown PHP service';
                // $result->version = ???;

                $result->agent = new NameVersionData('php', ElasticApm::VERSION);
                $result->language = new NameVersionData('PHP', PHP_VERSION);
                $result->runtime = $result->language;

                return $result;
            }
        })->discoverImpl();
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
