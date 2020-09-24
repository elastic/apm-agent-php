<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Psr\Http\Message\ResponseInterface;
use React\Http\Response;

trait HttpServerProcessTrait
{
    /**
     * @param callable $getRequestHeaderFunc
     *
     * @return ResponseInterface
     *
     * @phpstan-param callable(string): string $getRequestHeaderFunc
     */
    protected static function verifyServerIdEx(callable $getRequestHeaderFunc): ResponseInterface
    {
        $receivedServerId = $getRequestHeaderFunc(TestEnvBase::SERVER_ID_HEADER_NAME);
        if ($receivedServerId !== AmbientContext::config()->thisServerId()) {
            return self::buildErrorResponse(
                400,
                'Received server ID does not match the expected one.'
                . ' Expected: ' . AmbientContext::config()->thisServerId() . ', received: ' . $receivedServerId
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
            json_encode(['message' => $message], JSON_PRETTY_PRINT)
        );
    }
}
