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
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\DbgUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @template        T of ExecutionSegment
 *
 * @extends         ContextDataWrapper<T>
 */
abstract class ExecutionSegmentContext extends ContextDataWrapper implements ExecutionSegmentContextInterface
{
    /** @var ExecutionSegmentContextData */
    private $data;

    /** @var Logger */
    private $logger;

    protected function __construct(ExecutionSegment $owner, ExecutionSegmentContextData $data)
    {
        parent::__construct($owner);
        $this->data = $data;
        $this->logger = $this->tracer()->loggerFactory()
                             ->loggerForClass(LogCategory::PUBLIC_API, __NAMESPACE__, __CLASS__, __FILE__)
                             ->addContext('this', $this);
    }

    /** @inheritDoc */
    public function setLabel(string $key, $value): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        if (!self::doesValueHaveSupportedLabelType($value)) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Value for label is of unsupported type - it will be discarded',
                ['value type' => DbgUtil::getType($value), 'key' => $key, 'value' => $value]
            );
            return;
        }

        $this->data->labels[Tracer::limitKeywordString($key)] = is_string($value)
            ? Tracer::limitKeywordString($value)
            : $value;
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    public static function doesValueHaveSupportedLabelType($value): bool
    {
        return is_null($value) || is_string($value) || is_bool($value) || is_int($value) || is_float($value);
    }
}
