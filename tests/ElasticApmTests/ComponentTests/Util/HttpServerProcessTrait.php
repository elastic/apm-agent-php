<?php

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\Util;

use Elastic\Apm\Impl\Util\JsonUtil;
use Psr\Http\Message\ResponseInterface;
use React\Http\Response;

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
