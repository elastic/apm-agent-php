<?php

/** @noinspection PhpUnusedAliasInspection */

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Closure;
use Elastic\Apm\Impl\Config\OptionMetadataBase;
use Elastic\Apm\Tests\Util\Deserialization\SerializationTestUtil;
use Elastic\Apm\Tests\ComponentTests\Util\SharedDataBase;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @template   T
 *
 * @extends OptionMetadataBase<?T>
 */
final class NullableCustomOptionMetadata extends OptionMetadataBase
{
    /**
     * @var Closure(string): T
     */
    private $parseFunc;

    /**
     * @param Closure $parseFunc
     *
     * @phpstan-param Closure(string): T $parseFunc
     */
    public function __construct(Closure $parseFunc)
    {
        parent::__construct(null);
        $this->parseFunc = $parseFunc;
    }

    /**
     * @param string $rawValue
     *
     * @return mixed
     *
     * @phpstan-return SharedDataBase
     *
     * @see SharedDataBase::deserializeFromJson
     */
    public function parse(string $rawValue)
    {
        return ($this->parseFunc)($rawValue);
    }
}
