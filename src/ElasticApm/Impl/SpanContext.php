<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\SpanContextHttpInterface;
use Elastic\Apm\SpanContextInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class SpanContext extends ExecutionSegmentContext implements SpanContextInterface
{
    use LoggableTrait;

    /** @var Span */
    private $owner;

    /** @var SpanContextData */
    private $data;

    /** @var SpanContextHttp|null */
    private $http = null;

    public function __construct(Span $owner, SpanContextData $data)
    {
        parent::__construct($owner, $data);
        $this->owner = $owner;
        $this->data = $data;
    }

    public function http(): SpanContextHttpInterface
    {
        if (is_null($this->http)) {
            $this->data->http = new SpanContextHttpData();
            $this->http = new SpanContextHttp($this->owner, $this->data->http);
        }

        return $this->http;
    }
}
