<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
interface ServiceDataInterface
{
    /**
     * Immutable name of the service emitting this event.
     * Valid characters are: 'a'-'z', 'A'-'Z', '0'-'9', '_' and '-'.
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L50
     */
    public function name(): ?string;

    /**
     * Version of the service emitting this event.
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L75
     */
    public function version(): ?string;

    /**
     * Environment name of the service, e.g. "production" or "staging".
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L56
     */
    public function environment(): ?string;

    /**
     * Name and version of the Elastic APM agent.
     * Name of the Elastic APM agent, e.g. "php".
     * Version of the Elastic APM agent, e.g."1.0.0".
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L10
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L15
     */
    public function agent(): ?NameVersionDataInterface;

    /**
     * Name and version of the web framework used.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L26
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L30
     */
    public function framework(): ?NameVersionDataInterface;

    /**
     * Name and version of the programming language used.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L40
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L44
     */
    public function language(): ?NameVersionDataInterface;

    /**
     * Name and version of the language runtime running this service.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L65
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json#L69
     */
    public function runtime(): ?NameVersionDataInterface;

    public function node(): ?ServiceNodeData;
}
