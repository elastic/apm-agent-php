<?php

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\AutoInstrument\InterceptedCallTrackerInterface;
use Elastic\Apm\AutoInstrument\RegistrationContextInterface;
use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\SpanInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class CurlAutoInstrumentation
{
    public static function register(RegistrationContextInterface $ctx): void
    {
        if (!extension_loaded('curl')) {
            return;
        }

        self::curlExec($ctx);
    }

    public static function curlExec(RegistrationContextInterface $ctx): void
    {
        $ctx->interceptCallsToFunction(
            'curl_exec',
            function (): InterceptedCallTrackerInterface {
                return new class implements InterceptedCallTrackerInterface {
                    use InterceptedCallTrackerTrait;

                    /** @var resource|null */
                    private $curlHandle;

                    /** @var SpanInterface */
                    private $span;

                    public function preHook(?object $interceptedCallThis, ...$interceptedCallArgs): void
                    {
                        self::assertInterceptedCallThisIsNull($interceptedCallThis, ...$interceptedCallArgs);

                        $this->curlHandle = count($interceptedCallArgs) != 0 && is_resource($interceptedCallArgs[0])
                            ? $interceptedCallArgs[0]
                            : null;

                        $this->span = ElasticApm::beginCurrentSpan(
                            'curl_exec',
                            Constants::SPAN_TYPE_EXTERNAL,
                            Constants::SPAN_TYPE_EXTERNAL_SUBTYPE_HTTP
                        );
                    }

                    public function postHook(
                        int $numberOfStackFramesToSkip,
                        bool $hasExitedByException,
                        $returnValueOrThrown
                    ): void {
                        if (!$hasExitedByException) {
                            if (!is_null($this->curlHandle)) {
                                $info = curl_getinfo($this->curlHandle);
                                $httpCode = ArrayUtil::getValueIfKeyExistsElse('http_code', $info, null);
                                if (!is_null($httpCode)) {
                                    $this->span->setLabel('HTTP status', $httpCode);
                                }
                            }
                        }

                        self::endSpan(
                            $numberOfStackFramesToSkip + 1,
                            $this->span,
                            $hasExitedByException,
                            $returnValueOrThrown
                        );
                    }
                };
            }
        );
    }
}
