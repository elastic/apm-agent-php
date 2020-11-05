<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @template   T
 */
abstract class OptionMetadata implements LoggableInterface
{
    use LoggableTrait;

    /**
     * @return OptionParser
     *
     * @phpstan-return OptionParser<T>
     */
    abstract public function parser(): OptionParser;

    /**
     * @return mixed
     *
     * @phpstan-return T|null
     */
    abstract public function defaultValue();
}
