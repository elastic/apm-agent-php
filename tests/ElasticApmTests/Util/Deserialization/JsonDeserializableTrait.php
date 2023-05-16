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

namespace ElasticApmTests\Util\Deserialization;

use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\JsonUtil;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\TestCaseBase;

trait JsonDeserializableTrait
{
    /**
     * @param array<string, mixed> $decodedJson
     */
    public function deserializeFromDecodedJson(array $decodedJson): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        $thisClassName = ClassNameUtil::fqToShort(get_called_class());
        $dbgCtx->add(['thisClassName' => $thisClassName]);
        $dbgCtx->pushSubScope();
        foreach ($decodedJson as $jsonKey => $jsonVal) {
            $dbgCtx->clearCurrentSubScope(['jsonKey' => $jsonKey, 'jsonVal' => $jsonVal]);
            TestCaseBase::assertIsString($jsonKey);
            TestCaseBase::assertTrue(property_exists($this, $jsonKey));
            $this->$jsonKey = $this->deserializePropertyValue($jsonKey, $jsonVal);
        }
        $dbgCtx->popSubScope();
    }

    /**
     * @param string $propertyName
     * @param mixed  $decodedJson
     *
     * @return mixed
     *
     * @noinspection PhpUnusedParameterInspection
     */
    protected function deserializePropertyValue(string $propertyName, $decodedJson)
    {
        return $decodedJson;
    }

    public function serializeToString(): string
    {
        return SerializationUtil::serializeAsJson($this);
    }

    /**
     * @param string $serializedToString
     */
    public function deserializeFromString(string $serializedToString): void
    {
        $decodedJson = JsonUtil::decode($serializedToString, /* asAssocArray */ true);
        /** @var array<string, mixed> $decodedJson */
        $this->deserializeFromDecodedJson($decodedJson);
    }
}
