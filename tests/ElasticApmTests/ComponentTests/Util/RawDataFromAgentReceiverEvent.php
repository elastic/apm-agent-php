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

namespace ElasticApmTests\ComponentTests\Util;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use ElasticApmTests\Util\Deserialization\JsonSerializableTrait;
use JsonSerializable;
use PHPUnit\Framework\Assert;

abstract class RawDataFromAgentReceiverEvent implements JsonSerializable, LoggableInterface
{
    use JsonSerializableTrait;
    use LoggableTrait;

    /** @var string */
    public $shortClassName;

    public function __construct()
    {
        $this->shortClassName = ClassNameUtil::fqToShort(get_called_class());
    }

    abstract public function visit(RawDataFromAgentReceiverEventVisitorInterface $visitor): void;

    /**
     * @param array<string, mixed> $decodedJson
     */
    public static function deserializeFromDecodedJson(array $decodedJson): self
    {
        Assert::assertArrayHasKey('shortClassName', $decodedJson);
        $shortClassName = $decodedJson['shortClassName'];

        if ($shortClassName === ClassNameUtil::fqToShort(RawDataFromAgentReceiverEventConnectionStarted::class)) {
            return RawDataFromAgentReceiverEventConnectionStarted::leafDeserializeFromDecodedJson($decodedJson);
        }
        if ($shortClassName === ClassNameUtil::fqToShort(RawDataFromAgentReceiverEventRequest::class)) {
            return RawDataFromAgentReceiverEventRequest::leafDeserializeFromDecodedJson($decodedJson);
        }

        Assert::fail(LoggableToString::convert(['shortClassName' => $shortClassName, 'decodedJson' => $decodedJson]));
    }
}
