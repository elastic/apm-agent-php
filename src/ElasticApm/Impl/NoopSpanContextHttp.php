<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Util\NoopObjectTrait;
use Elastic\Apm\SpanContextHttpInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class NoopSpanContextHttp implements SpanContextHttpInterface, LoggableInterface
{
    use NoopObjectTrait;

    /** @inheritDoc */
    public function setUrl(?string $url): void
    {
    }

    /** @inheritDoc */
    public function setStatusCode(?int $statusCode): void
    {
    }

    /** @inheritDoc */
    public function setMethod(?string $method): void
    {
    }
}
