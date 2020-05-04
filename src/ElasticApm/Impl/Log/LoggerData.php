<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Log;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class LoggerData
{
    /** @var string */
    public $className;

    /** @var string */
    public $sourceCodeFile;

    /** @var array<mixed> */
    public $attachedContext = [];

    /** @var Backend */
    public $backend;

    public function __construct(string $className, string $sourceCodeFile, Backend $backend)
    {
        $this->className = $className;
        $this->sourceCodeFile = $sourceCodeFile;
        $this->backend = $backend;
    }
}
