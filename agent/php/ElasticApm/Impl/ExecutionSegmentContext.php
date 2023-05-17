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

use Elastic\Apm\ExecutionSegmentContextInterface;
use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\DbgUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @template T of ExecutionSegment
 *
 * @extends ContextPartWrapper<T>
 */
abstract class ExecutionSegmentContext extends ContextPartWrapper implements ExecutionSegmentContextInterface
{
    /** @var ?array<string, string|bool|int|float|null> */
    public $labels = null;

    /** @var Logger */
    private $logger;

    protected function __construct(ExecutionSegment $owner)
    {
        parent::__construct($owner);
        $this->logger = $this->tracer()->loggerFactory()
                             ->loggerForClass(LogCategory::PUBLIC_API, __NAMESPACE__, __CLASS__, __FILE__)
                             ->addContext('this', $this);
    }

    /**
     * @param string                                     $key
     * @param string|bool|int|float|null                 $value
     * @param bool                                       $enforceKeywordString
     * @param ?array<string, string|bool|int|float|null> $map
     * @param string                                     $dbgMapName
     *
     * @return void
     */
    protected function setInKeyValueMap(
        string $key,
        $value,
        bool $enforceKeywordString,
        ?array &$map,
        string $dbgMapName
    ): void {
        if ($this->beforeMutating()) {
            return;
        }

        if (!self::doesValueHaveSupportedLabelType($value)) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Value for ' . $dbgMapName . ' is of unsupported type - it will be discarded',
                ['value type' => DbgUtil::getType($value), 'key' => $key, 'value' => $value]
            );
            return;
        }

        if ($map === null) {
            $map = [];
        }

        $map[$this->tracer()->limitString($key, $enforceKeywordString)] = is_string($value)
            ? $this->tracer()->limitString($value, $enforceKeywordString)
            : $value;
    }

    /** @inheritDoc */
    public function setLabel(string $key, $value): void
    {
        $this->setInKeyValueMap($key, $value, /* enforceKeywordString */ true, /* ref */ $this->labels, 'label');
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    public static function doesValueHaveSupportedLabelType($value): bool
    {
        return $value === null || is_string($value) || is_bool($value) || is_int($value) || is_float($value);
    }

    /** @inheritDoc */
    public function prepareForSerialization(): bool
    {
        return $this->labels !== null && !ArrayUtil::isEmpty($this->labels);
    }

    /** @inheritDoc */
    public function jsonSerialize()
    {
        $result = [];

        // APM Server Intake API expects 'tags' key for labels
        // https://github.com/elastic/apm-server/blob/7.0/docs/spec/context.json#L46
        // https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L88
        if ($this->labels !== null) {
            SerializationUtil::addNameValueIfNotEmpty('tags', $this->labels, /* ref */ $result);
        }

        return SerializationUtil::postProcessResult($result);
    }
}
