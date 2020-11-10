<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\SpanContextHttpInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class SpanContextHttp extends ContextDataWrapper implements SpanContextHttpInterface
{
    /** @var SpanContextHttpData */
    private $data;

    public function __construct(Span $owner, SpanContextHttpData $data)
    {
        parent::__construct($owner);
        $this->data = $data;
    }

    /** @inheritDoc */
    public function setUrl(?string $url): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->data->url = $url;
    }

    /** @inheritDoc */
    public function setStatusCode(?int $statusCode): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->data->statusCode = $statusCode;
    }

    /** @inheritDoc */
    public function setMethod(?string $method): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->data->method = Tracer::limitNullableKeywordString($method);
    }
}
