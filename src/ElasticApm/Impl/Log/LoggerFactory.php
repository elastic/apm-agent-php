<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Log;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class LoggerFactory
{
    /** @var Backend */
    private $backend;

    public function __construct(Backend $backend)
    {
        $this->backend = $backend;
    }

    public function loggerForClass(string $className, string $sourceCodeFile): Logger
    {
        return new Logger($className, $sourceCodeFile, $this->backend);
    }
}
