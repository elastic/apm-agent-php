<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Util\ObjectToStringUsingPropertiesTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @implements OptionParserInterface<string>
 */
final class StringOptionParser implements OptionParserInterface
{
    use ObjectToStringUsingPropertiesTrait;

    /**
     * @param string $rawValue
     *
     * @return string
     */
    public function parse(string $rawValue)
    {
        return $rawValue;
    }
}
