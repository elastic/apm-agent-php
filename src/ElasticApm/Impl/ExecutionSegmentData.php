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

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class ExecutionSegmentData implements SerializableDataInterface, LoggableInterface
{
    use LoggableTrait;

    /** @var string */
    public $name;

    /** @var string */
    public $type;

    /** @var string */
    public $id;

    /** @var string */
    public $traceId;

    /** @var float UTC based and in microseconds since Unix epoch */
    public $timestamp;

    /** @var float  In milliseconds with 3 decimal points */
    public $duration;

    /** @var string|null */
    public $outcome = null;

    /** @inheritDoc */
    public function jsonSerialize()
    {
        $result = [];

        SerializationUtil::addNameValue('name', $this->name, /* ref */ $result);
        SerializationUtil::addNameValue('type', $this->type, /* ref */ $result);
        SerializationUtil::addNameValue('id', $this->id, /* ref */ $result);
        SerializationUtil::addNameValue('trace_id', $this->traceId, /* ref */ $result);
        SerializationUtil::addNameValue(
            'timestamp',
            SerializationUtil::adaptTimestamp($this->timestamp),
            /* ref */ $result
        );
        SerializationUtil::addNameValue('duration', $this->duration, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('outcome', $this->outcome, /* ref */ $result);

        return SerializationUtil::postProcessResult($result);
    }
}
