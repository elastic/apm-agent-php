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
    protected static function verifyServerId(string $receivedServerId): ResponseInterface
    {
        if ($receivedServerId !== AmbientContext::testConfig()->sharedDataPerProcess->thisServerId) {
            return self::buildErrorResponse(
                400,
                'Received server ID does not match the expected one.'
                . ' Expected: ' . AmbientContext::testConfig()->sharedDataPerProcess->thisServerId
                . ', received: ' . $receivedServerId
            );
        }

        return new Response(HttpConsts::STATUS_OK);
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
}
