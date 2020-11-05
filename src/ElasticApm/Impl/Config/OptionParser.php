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
abstract class OptionParser implements LoggableInterface
{
    use LoggableTrait;

    /**
     * @param string $rawValue
     *
     * @return mixed
     * @throws ParseException
     *
     * @phpstan-return T
     */
    abstract public function parse(string $rawValue);
}
