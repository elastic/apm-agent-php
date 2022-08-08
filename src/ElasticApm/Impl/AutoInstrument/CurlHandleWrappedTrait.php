<?php

/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\LogStreamInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
trait CurlHandleWrappedTrait
{
    use LoggableTrait;

    /**
     * Prior to PHP 8 $curlHandle is a resource.
     * For PHP 8+ $curlHandle is an instance of CurlHandle class.
     *
     * @var resource|object
     */
    private $curlHandle;

    /**
     * @param resource|object $curlHandle
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function constructCurlHandleWrappedTrait($curlHandle): void
    {
        $this->curlHandle = $curlHandle;
    }

    /**
     * @param mixed $val
     *
     * @return bool
     */
    public static function isValidValue($val): bool
    {
        return (PHP_MAJOR_VERSION < 8) ? is_resource($val) : is_object($val);
    }

    /**
     * @return mixed
     */
    public function getResponseStatusCode()
    {
        return curl_getinfo($this->curlHandle, CURLINFO_RESPONSE_CODE); // @phpstan-ignore-line
    }

    public function errno(): int
    {
        return curl_errno($this->curlHandle); // @phpstan-ignore-line
    }

    public function error(): string
    {
        return curl_error($this->curlHandle); // @phpstan-ignore-line
    }

    /**
     * @param int   $option
     * @param mixed $value
     *
     * @return bool
     */
    public function setOpt(int $option, $value): bool
    {
        return curl_setopt($this->curlHandle, $option, $value); // @phpstan-ignore-line
    }

    public function asInt(): int
    {
        return is_resource($this->curlHandle) ? intval($this->curlHandle) : spl_object_id($this->curlHandle);
    }

    public static function nullableAsInt(?self $curlHandleWrapped): ?int
    {
        return $curlHandleWrapped === null ? null : $curlHandleWrapped->asInt();
    }

    public function toLog(LogStreamInterface $stream): void
    {
        $stream->toLogAs(['as int' => $this->asInt()]);
    }
}
