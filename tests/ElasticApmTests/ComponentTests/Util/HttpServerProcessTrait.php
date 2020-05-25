<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use React\Http\Response;
use RuntimeException;

trait HttpServerProcessTrait
{
    /**
     * @param int                   $port
     * @param string                $httpMethod
     * @param string                $uriPath
     * @param array<string, string> $headers
     *
     * @return ResponseInterface
     *
     * @throws GuzzleException
     * @noinspection PhpDocRedundantThrowsInspection
     */
    public static function sendRequest(
        int $port,
        string $httpMethod,
        string $uriPath,
        array $headers
    ): ResponseInterface {
        $client = new Client(['base_uri' => "http://localhost:$port"]);
        return $client->request($httpMethod, $uriPath, [RequestOptions::HEADERS => $headers]);
    }

    public static function sendRequestToCheckStatus(int $port, string $testEnvId, string $dbgServerDesc): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $response = self::sendRequest(
            $port,
            HttpConsts::METHOD_GET,
            TestEnvBase::STATUS_CHECK_URI,
            [TestEnvBase::TEST_ENV_ID_HEADER_NAME => $testEnvId]
        );

        if ($response->getStatusCode() !== HttpConsts::STATUS_OK) {
            throw new RuntimeException("Received unexpected status code in response to status check to $dbgServerDesc");
        }
    }

    /**
     * @param callable $getRequestHeaderFunc
     *
     * @return ResponseInterface
     *
     * @phpstan-param callable(string): string $getRequestHeaderFunc
     */
    protected static function verifyTestEnvIdEx(callable $getRequestHeaderFunc): ResponseInterface
    {
        $receivedTestEnvId = $getRequestHeaderFunc(TestEnvBase::TEST_ENV_ID_HEADER_NAME);
        if ($receivedTestEnvId !== AmbientContext::config()->testEnvId()) {
            return self::buildErrorResponse(
                400,
                'Received test env ID does not match the expected one.'
                . ' Expected: ' . AmbientContext::config()->testEnvId() . ', received: ' . $receivedTestEnvId
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
