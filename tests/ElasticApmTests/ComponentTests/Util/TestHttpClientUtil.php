<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Util\StaticClassTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

final class TestHttpClientUtil
{
    use StaticClassTrait;

    /**
     * @param int                   $port
     * @param string                $serverId
     * @param string                $httpMethod
     * @param string                $uriPath
     * @param array<string, string> $headers
     *
     * @return ResponseInterface
     *
     * @throws GuzzleException
     * @noinspection PhpDocRedundantThrowsInspection
     */
    public static function sendHttpRequest(
        int $port,
        string $serverId,
        string $httpMethod,
        string $uriPath,
        array $headers = []
    ): ResponseInterface {
        $client = new Client(['base_uri' => "http://localhost:$port"]);
        return $client->request(
            $httpMethod,
            $uriPath,
            [RequestOptions::HEADERS => $headers + [TestEnvBase::SERVER_ID_HEADER_NAME => $serverId]]
        );
    }
}
