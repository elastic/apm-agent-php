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
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\JsonUtil;
use Elastic\Apm\Impl\Util\UrlParts;
use GuzzleHttp\Exception\GuzzleException;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

class HttpServerHandle implements LoggableInterface
{
    use LoggableTrait;

    public const DEFAULT_HOST = '127.0.0.1';
    public const STATUS_CHECK_URI_PATH = '/elastic_apm_php_tests_status_check';
    public const PID_KEY = 'pid';

    /** @var string */
    private $dbgServerDesc;

    /** @var int */
    private $spawnedProcessOsId;

    /** @var string */
    private $spawnedProcessInternalId;

    /** @var int[] */
    private $ports;

    /**
     * @param string $dbgServerDesc
     * @param int    $spawnedProcessOsId
     * @param string $spawnedProcessInternalId
     * @param int[]  $ports
     */
    public function __construct(
        string $dbgServerDesc,
        int $spawnedProcessOsId,
        string $spawnedProcessInternalId,
        array $ports
    ) {
        $this->dbgServerDesc = $dbgServerDesc;
        $this->spawnedProcessOsId = $spawnedProcessOsId;
        $this->spawnedProcessInternalId = $spawnedProcessInternalId;
        $this->ports = $ports;
    }

    public function getSpawnedProcessOsId(): int
    {
        return $this->spawnedProcessOsId;
    }

    public function getSpawnedProcessInternalId(): string
    {
        return $this->spawnedProcessInternalId;
    }

    /**
     * @return int[]
     */
    public function getPorts(): array
    {
        return $this->ports;
    }

    public function getMainPort(): int
    {
        Assert::assertNotEmpty($this->ports);
        return $this->ports[0];
    }

    /**
     * @param string                $httpMethod
     * @param string                $path
     * @param array<string, string> $headers
     *
     * @return ResponseInterface
     *
     * @throws GuzzleException
     */
    public function sendRequest(string $httpMethod, string $path, array $headers = []): ResponseInterface
    {
        return HttpClientUtilForTests::sendRequest(
            $httpMethod,
            (new UrlParts())->path($path)->port($this->getMainPort()),
            TestInfraDataPerRequest::withSpawnedProcessInternalId($this->spawnedProcessInternalId),
            $headers
        );
    }

    public function signalAndWaitForItToExit(): void
    {
        $response = $this->sendRequest(
            HttpConstantsForTests::METHOD_POST,
            TestInfraHttpServerProcessBase::EXIT_URI_PATH
        );
        Assert::assertSame(HttpConstantsForTests::STATUS_OK, $response->getStatusCode());

        $hasExited = ProcessUtilForTests::waitForProcessToExit(
            $this->dbgServerDesc,
            $this->spawnedProcessOsId,
            10 * 1000 * 1000 /* <- maxWaitTimeInMicroseconds - 10 seconds */
        );
        Assert::assertTrue($hasExited);
    }

    public function serialize(): string
    {
        return JsonUtil::encode(get_object_vars($this));
    }

    public static function deserialize(string $serialized): HttpServerHandle
    {
        $decodeJson = JsonUtil::decode($serialized, /* asAssocArray: */ true);
        Assert::assertIsArray($decodeJson);

        $getDecodedIntValue = function (string $propName) use ($decodeJson): int {
            Assert::assertArrayHasKey($propName, $decodeJson);
            Assert::assertIsInt($decodeJson[$propName]);
            return $decodeJson[$propName];
        };

        $getDecodedStringValue = function (string $propName) use ($decodeJson): string {
            Assert::assertArrayHasKey($propName, $decodeJson);
            Assert::assertIsString($decodeJson[$propName]);
            return $decodeJson[$propName];
        };

        /**
         * @param string $propName
         *
         * @return int[]
         */
        $getDecodedIntArrayValue = function (string $propName) use ($decodeJson): array {
            Assert::assertArrayHasKey($propName, $decodeJson, LoggableToString::convert(['decodeJson' => $decodeJson]));
            $propVal = $decodeJson[$propName];
            Assert::assertIsArray($propVal);
            Assert::assertNotEmpty($propVal);
            Assert::assertNotEmpty(ArrayUtil::isList($propVal));
            foreach ($propVal as $arrayElement) {
                Assert::assertIsInt($arrayElement);
            }
            return $propVal;
        };

        return new self(
            $getDecodedStringValue('dbgServerDesc'),
            $getDecodedIntValue('spawnedProcessOsId'),
            $getDecodedStringValue('spawnedProcessInternalId'),
            $getDecodedIntArrayValue('ports')
        );
    }
}
