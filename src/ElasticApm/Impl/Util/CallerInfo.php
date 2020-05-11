<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class CallerInfo
{
    /** @var string|null */
    public $file;

    /** @var int|null */
    public $line;

    /** @var string|null */
    public $class;

    /** @var string|null */
    public $function;

    public function __construct(?string $file, ?int $line, ?string $class, ?string $function)
    {
        $this->file = $file;
        $this->line = $line;
        $this->class = $class;
        $this->function = $function;
    }

    public function __toString(): string
    {
        $result = '';
        if (!is_null($this->class)) {
            $result .= $this->class;
            $result .= '::';
        }

        $result .= $this->function ?? '<UNKNOWN FUNCTION>';

        $result .= ' at ';
        $result .= is_null($this->file) ? '<UNKNOWN FILE>' : DbgUtil::formatSourceCodeFilePath($this->file);
        $result .= ':';
        $result .= $this->line ?? '<UNKNOWN LINE>';

        return $result;
    }
}
