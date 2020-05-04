<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
interface NameVersionDataInterface
{
    /**
     * Name of an entity.
     *
     * The length of this string is limited to 1024.
     */
    public function name(): ?string;

    /**
     * Version of an entity, e.g."1.0.0".
     *
     * The length of this string is limited to 1024.
     */
    public function version(): ?string;
}
