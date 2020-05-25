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

    public function loggerForClass(
        string $category,
        string $namespace,
        string $className,
        string $srcCodeFile
    ): Logger {
        return Logger::makeRoot($category, $namespace, $className, $srcCodeFile, $this->backend);
    }
}
