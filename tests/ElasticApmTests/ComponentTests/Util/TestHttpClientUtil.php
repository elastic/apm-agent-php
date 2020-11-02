<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\BackendComm\SerializationUtil;
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
     * @param string                $httpMethod
     * @param string                $uriPath
     * @param SharedDataPerRequest  $sharedDataPerRequest
     * @param array<string, string> $headers
     *
     * @return ResponseInterface
     *
     * @throws GuzzleException
     */
    public static function sendHttpRequest(
        int $port,
        string $httpMethod,
        string $uriPath,
        SharedDataPerRequest $sharedDataPerRequest,
        array $headers = []
    ): ResponseInterface {
        $client = new Client(['base_uri' => "http://localhost:$port"]);
        return $client->request(
            $httpMethod,
            $uriPath,
            [
                RequestOptions::HEADERS     =>
                    $headers
                    + [
                        RequestHeadersRawSnapshotSource::optionNameToHeaderName(
                            AllComponentTestsOptionsMetadata::SHARED_DATA_PER_REQUEST_OPTION_NAME
                        ) => SerializationUtil::serializeAsJson($sharedDataPerRequest, SharedDataPerRequest::class),
                    ],
                /*
                 * http://docs.guzzlephp.org/en/stable/request-options.html#http-errors
                 *
                 * http_errors
                 *
                 * Set to false to disable throwing exceptions on an HTTP protocol errors (i.e., 4xx and 5xx responses).
                 * Exceptions are thrown by default when HTTP protocol errors are encountered.
                 */
                RequestOptions::HTTP_ERRORS => false,
            ]
        );
    }
}
