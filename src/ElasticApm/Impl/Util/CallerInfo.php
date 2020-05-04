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
    /** @var string */
    public $file;

    /** @var int */
    public $line;

    /** @var string|null */
    public $class;

    /** @var string */
    public $function;

    public function __construct(string $file, int $line, ?string $class, string $function)
    {
        $this->file = $file;
        $this->line = $line;
        $this->class = $class;
        $this->function = $function;
    }
}
