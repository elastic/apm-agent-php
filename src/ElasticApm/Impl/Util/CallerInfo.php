<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggablePhpStacktrace;
use Elastic\Apm\Impl\Log\LoggableTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class CallerInfo implements LoggableInterface
{
    use LoggableTrait;

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
        $this->file = is_null($file) ? null : LoggablePhpStacktrace::adaptSourceCodeFilePath($file);
        $this->line = $line;
        $this->class = $class;
        $this->function = $function;
    }
}
