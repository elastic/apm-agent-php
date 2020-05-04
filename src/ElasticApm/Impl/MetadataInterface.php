<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
interface MetadataInterface
{
    /**
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/metadata.json#L22
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/process.json
     */
    public function process(): ProcessDataInterface;

    /**
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/metadata.json#L7
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/service.json
     */
    public function service(): ServiceDataInterface;
}
