<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\BackendComm\SerializationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class SpanContextData extends ExecutionSegmentContextData
{
    /**
     * @var SpanContextHttpData|null
     *
     * An object containing contextual data of the related http request
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L69
     */
    public $http = null;

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return parent::isEmpty() && (is_null($this->http) || $this->http->isEmpty());
    }

    public function jsonSerialize()
    {
        $result = parent::jsonSerialize();

        SerializationUtil::addNameValueIfNotNull('http', $this->http, /* ref */ $result);

        return $result;
    }
}
