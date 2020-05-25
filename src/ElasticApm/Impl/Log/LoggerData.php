<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Log;

use Elastic\Apm\Impl\Util\DbgUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class LoggerData
{
    /** @var string */
    public $category;

    /** @var string */
    public $namespace;

    /** @var string */
    public $fqClassName;

    /** @var string */
    public $srcCodeFile;

    /** @var LoggerData|null */
    public $inheritedData;

    /** @var array<string, mixed> */
    public $context = [];

    /** @var Backend */
    public $backend;

    private function __construct(
        string $category,
        string $namespace,
        string $fqClassName,
        string $srcCodeFile,
        Backend $backend,
        ?LoggerData $inheritedData
    ) {
        $this->category = $category;
        $this->namespace = $namespace;
        $this->fqClassName = $fqClassName;
        $this->srcCodeFile = $srcCodeFile;
        $this->backend = $backend;
        $this->inheritedData = $inheritedData;
    }

    public static function makeRoot(
        string $category,
        string $namespace,
        string $fqClassName,
        string $srcCodeFile,
        Backend $backend
    ): self {
        return new self(
            $category,
            $namespace,
            $fqClassName,
            $srcCodeFile,
            $backend,
            /* inheritedData */ null
        );
    }

    public static function inherit(self $inheritedData): self
    {
        return new self(
            $inheritedData->category,
            $inheritedData->namespace,
            $inheritedData->fqClassName,
            $inheritedData->srcCodeFile,
            $inheritedData->backend,
            $inheritedData
        );
    }
}
