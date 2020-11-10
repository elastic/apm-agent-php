<?php

declare(strict_types=1);

namespace Elastic\Apm;

interface SpanContextInterface extends ExecutionSegmentContextInterface
{
    /**
     * Returns an object containing contextual data of the related http request
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L69
     */
    public function http(): SpanContextHttpInterface;
}
