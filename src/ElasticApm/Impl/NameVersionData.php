<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class NameVersionData extends EventData implements NameVersionDataInterface, LoggableInterface
{
    use LoggableTrait;

    /** @var string|null */
    protected $name;

    /** @var string|null */
    protected $version;

    public function __construct(?string $name = null, ?string $version = null)
    {
        $this->name = $name;
        $this->version = $version;
    }

    /** @inheritDoc */
    public function name(): ?string
    {
        return $this->name;
    }

    /** @inheritDoc */
    public function version(): ?string
    {
        return $this->version;
    }
}
