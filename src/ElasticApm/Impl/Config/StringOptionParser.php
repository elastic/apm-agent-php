<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends OptionParser<string>
 */
final class StringOptionParser extends OptionParser
{
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
