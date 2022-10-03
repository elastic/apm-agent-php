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

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Util\ArrayUtil;

/**
 * An error or a logged error message captured by an agent occurring in a monitored service
 *
 * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json
 *
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class MetricSet implements SerializableDataInterface, LoggableInterface
{
    use LoggableTrait;

    /**
     * @var float
     *
     * UTC based and in microseconds since Unix epoch
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/metricsets/metricset.json#L6
     */
    public $timestamp;

    /**
     * @var ?string
     *
     * @see  TransactionInterface::setName() For the description.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.3.0/docs/spec/transaction_name.json#L6
     */
    public $transactionName = null;

    /**
     * @var ?string
     *
     * @see  TransactionInterface::setType() For the description.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.3.0/docs/spec/transaction_type.json#L6
     */
    public $transactionType = null;

    /**
     * @var ?string
     *
     * @see  SpanInterface::setType() For the description.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.3.0/docs/spec/span_type.json#L6
     */
    public $spanType = null;

    /**
     * @var ?string
     *
     * @see  SpanInterface::setSubtype() For the description.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.3.0/docs/spec/span_subtype.json
     */
    public $spanSubtype = null;

    /**
     * @var array<string, array<string, float|int>>
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/metricsets/metricset.json#L9
     */
    public $samples = [];

    /**
     * @param string    $key
     * @param float|int $value
     */
    public function setSample(string $key, $value): void
    {
        if (array_key_exists($key, $this->samples)) {
            $this->samples[$key] = ['value' => $value];
        } else {
            $this->samples[$key]['value'] = $value;
        }
    }

    /**
     * @param string $key
     *
     * @return float|int|null
     */
    public function getSample(string $key)
    {
        return array_key_exists($key, $this->samples) ? $this->samples[$key]['value'] : null;
    }

    public function clearSamples(): void
    {
        $this->samples = [];
    }

    public function samplesCount(): int
    {
        return count($this->samples);
    }

    /** @inheritDoc */
    public function jsonSerialize()
    {
        $result = [];

        SerializationUtil::addNameValue(
            'timestamp',
            SerializationUtil::adaptTimestamp($this->timestamp),
            /* ref */ $result
        );

        $transactionObj = [];
        SerializationUtil::addNameValueIfNotNull('name', $this->transactionName, /* ref */ $transactionObj);
        SerializationUtil::addNameValueIfNotNull('type', $this->transactionType, /* ref */ $transactionObj);
        if (!ArrayUtil::isEmpty($transactionObj)) {
            SerializationUtil::addNameValueIfNotNull('transaction', $transactionObj, /* ref */ $result);
        }

        $spanObj = [];
        SerializationUtil::addNameValueIfNotNull('type', $this->spanType, /* ref */ $spanObj);
        SerializationUtil::addNameValueIfNotNull('subtype', $this->spanSubtype, /* ref */ $spanObj);
        if (!ArrayUtil::isEmpty($spanObj)) {
            SerializationUtil::addNameValueIfNotNull('span', $spanObj, /* ref */ $result);
        }

        SerializationUtil::addNameValue(
            'samples',
            SerializationUtil::ensureObject($this->samples),
            /* ref */ $result
        );

        return SerializationUtil::postProcessResult($result);
    }
}
