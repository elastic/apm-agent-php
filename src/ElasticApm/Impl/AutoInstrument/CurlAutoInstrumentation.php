<?php

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\AutoInstrument\RegistrationContextInterface;
use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class CurlAutoInstrumentation
{
    use AutoInstrumentationTrait;

    public static function register(RegistrationContextInterface $ctx): void
    {
        self::curlExec($ctx);
    }

    public static function curlExec(RegistrationContextInterface $ctx): void
    {
        $ctx->interceptCallsToFunction(
            'curl_exec',
            function (...$interceptedCallArgs): callable {
                /** @var resource|null */
                $curlHandle = count($interceptedCallArgs) != 0 && is_resource($interceptedCallArgs[0])
                    ? $interceptedCallArgs[0]
                    : null;

                $span = ElasticApm::beginCurrentSpan(
                    'curl_exec', // TODO: Sergey Kleyman: fetch HTTP method + path for span name
                    Constants::SPAN_TYPE_EXTERNAL,
                    Constants::SPAN_TYPE_EXTERNAL_SUBTYPE_HTTP
                );

                /**
                 * @param bool            $hasExitedByException
                 * @param mixed|Throwable $returnValueOrThrown Return value of the intercepted call or thrown object
                 */
                return function (bool $hasExitedByException, $returnValueOrThrown) use ($span, $curlHandle): void {
                    if (!$hasExitedByException) {
                        if (!is_null($curlHandle)) {
                            $httpCode = null;
                            $info = curl_getinfo($curlHandle);
                            if (ArrayUtil::getValueIfKeyExists('http_code', $info, /* ref */ $httpCode)) {
                                // TODO: Sergey Kleyman: use the corresponding property in Intake API
                                $span->setLabel('HTTP status', $httpCode);
                            }
                        }
                    }
                    self::endSpan($span, $hasExitedByException, $returnValueOrThrown);
                };
            }
        );
    }
}
