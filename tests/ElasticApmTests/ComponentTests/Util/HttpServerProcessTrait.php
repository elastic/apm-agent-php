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

use Elastic\Apm\Impl\Util\JsonUtil;
use Psr\Http\Message\ResponseInterface;
use React\Http\Message\Response;

trait HttpServerProcessTrait
{
    protected static function verifySpawnedProcessInternalId(
        string $receivedSpawnedProcessInternalId
    ): ?ResponseInterface {
        $expectedSpawnedProcessInternalId
            = AmbientContextForTests::testConfig()->dataPerProcess->thisSpawnedProcessInternalId;
        if ($expectedSpawnedProcessInternalId !== $receivedSpawnedProcessInternalId) {
            return self::buildErrorResponse(
                HttpConstantsForTests::STATUS_BAD_REQUEST,
                'Received server ID does not match the expected one.'
                . ' Expected: ' . $expectedSpawnedProcessInternalId
                . ', received: ' . $receivedSpawnedProcessInternalId
            );
        }

        return null;
    }

    protected static function buildErrorResponse(int $status, string $message): ResponseInterface
    {
        return new Response(
            $status,
            // headers:
            [
                'Content-Type' => 'application/json',
            ],
            // body:
            JsonUtil::encode(['message' => $message], /* prettyPrint: */ true)
        );
    }

    protected static function buildDefaultResponse(): ResponseInterface
    {
        return new Response();
    }

    protected static function buildResponseWithPid(): ResponseInterface
    {
        return Response::json([HttpServerHandle::PID_KEY => getmypid()]);
    }
}
