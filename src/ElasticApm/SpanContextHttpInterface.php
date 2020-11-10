<?php

declare(strict_types=1);

namespace Elastic\Apm;

interface SpanContextHttpInterface
{
    /**
     * The raw url of the correlating http request
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L73
     *
     * @param string|null $url
     *
     * @return void
     */
    public function setUrl(?string $url): void;

    /**
     * The status code of the http request
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L77
     *
     * @param int|null $statusCode
     *
     * @return void
     */
    public function setStatusCode(?int $statusCode): void;

    /**
     * The method of the http request
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L81
     *
     * @param string|null $method
     *
     * @return void
     */
    public function setMethod(?string $method): void;
}
