<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Closure;
use Elastic\Apm\Impl\Config\RawSnapshotFromArray;
use Elastic\Apm\Impl\Config\RawSnapshotInterface;
use Elastic\Apm\Impl\Config\RawSnapshotSourceInterface;

final class RequestHeadersRawSnapshotSource implements RawSnapshotSourceInterface
{
    public const HEADER_NAMES_PREFIX = 'ELASTIC_APM_PHP_TESTS_';

    /** @var Closure(string): ?string */
    private $getHeaderValue;

    /**
     * @param Closure $getHeaderValue
     *
     * @phpstan-param Closure(string): ?string  $getHeaderValue
     */
    public function __construct(Closure $getHeaderValue)
    {
        $this->getHeaderValue = $getHeaderValue;
    }

    public static function optionNameToHeaderName(string $optionName): string
    {
        return self::HEADER_NAMES_PREFIX . strtoupper($optionName);
    }

    public function currentSnapshot(array $optionNameToMeta): RawSnapshotInterface
    {
        /** @var array<string, string> */
        $optionNameToHeaderValue = [];

        foreach ($optionNameToMeta as $optionName => $optionMeta) {
            $headerValue = ($this->getHeaderValue)(self::optionNameToHeaderName($optionName));
            if (!is_null($headerValue)) {
                $optionNameToHeaderValue[$optionName] = $headerValue;
            }
        }

        return new RawSnapshotFromArray($optionNameToHeaderValue);
    }
}
