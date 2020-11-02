<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Closure;
use Elastic\Apm\Impl\Config\OptionParserInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @template T
 *
 * @implements OptionParserInterface<T>
 */
final class CustomOptionParser implements OptionParserInterface
{
    /**
     * @var Closure(string): T
     */
    private $parseFunc;

    /**
     * @param Closure(string): T $parseFunc
     */
    public function __construct(Closure $parseFunc)
    {
        $this->parseFunc = $parseFunc;
    }

    /**
     * @param string $rawValue
     *
     * @return mixed
     *
     * @phpstan-return T
     */
    public function parse(string $rawValue)
    {
        return ($this->parseFunc)($rawValue);
    }
}
