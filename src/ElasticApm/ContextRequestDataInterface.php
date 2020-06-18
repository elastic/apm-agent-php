<?php

declare(strict_types=1);

namespace Elastic\Apm;

/**
 * If event was recorded as a result of a HTTP request,
 * the information from the request should be used to fill this object
 *
 * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/request.json
 * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/context.json#L43
 */
interface ContextRequestDataInterface
{
    /**
     * The raw, unparsed URL of the HTTP request line,
     * e.g https://example.com:443/search?q=elasticsearch.
     * This URL may be absolute or relative.
     * For more details, see https://www.w3.org/Protocols/rfc2616/rfc2616-sec5.html#sec5.1.2.
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/request.json#L54
     */
    public function getRawUrl(): ?string;
}
