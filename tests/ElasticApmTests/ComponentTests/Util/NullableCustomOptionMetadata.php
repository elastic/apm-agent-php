<?php

/** @noinspection PhpUnusedAliasInspection */

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\Util;

use Closure;
use Elastic\Apm\Impl\Config\NullableOptionMetadata;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @template   T
 *
 * @extends    NullableOptionMetadata<T>
 */
final class NullableCustomOptionMetadata extends NullableOptionMetadata
{
    /**
     * @param Closure $parseFunc
     *
     * @phpstan-param Closure(string): T $parseFunc
     */
    public function __construct(Closure $parseFunc)
    {
        parent::__construct(new CustomOptionParser($parseFunc));
    }
}
