<?php

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument\Curl;

use Elastic\Apm\AutoInstrument\RegistrationContextInterface;
use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\AutoInstrument\InterceptedCallToSpanBase;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Util\ArrayUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class CurlAutoInstrumentation
{
    public static function register(RegistrationContextInterface $ctx): void
    {
        self::curlExec($ctx);
    }

    public static function curlExec(RegistrationContextInterface $ctx): void
    {
        $ctx->interceptCallsToInternalFunction(
            'curl_exec',
            InterceptedCallToSpanBase::wrap(
                function (): InterceptedCallToSpanBase {
                    return new class extends InterceptedCallToSpanBase {
                        /** @var resource|null */
                        public $curlHandle;

                        /** @inheritDoc */
                        public function beginSpan(...$interceptedCallArgs): void
                        {
                            $this->curlHandle = count($interceptedCallArgs) != 0 && is_resource($interceptedCallArgs[0])
                                ? $interceptedCallArgs[0]
                                : null;

                            $this->span = ElasticApm::beginCurrentSpan(
                                'curl_exec', // TODO: Sergey Kleyman: fetch HTTP method + path for span name
                                Constants::SPAN_TYPE_EXTERNAL,
                                Constants::SPAN_TYPE_EXTERNAL_SUBTYPE_HTTP
                            );
                        }

                        /** @inheritDoc */
                        public function endSpan(bool $hasExitedByException, $returnValueOrThrown): void
                        {
                            if (!$hasExitedByException) {
                                if (!is_null($this->curlHandle)) {
                                    $httpCode = null;
                                    $info = curl_getinfo($this->curlHandle);
                                    if (ArrayUtil::getValueIfKeyExists('http_code', $info, /* ref */ $httpCode)) {
                                        $this->span->setLabel('HTTP status', $httpCode);
                                    }
                                }
                            }

                            parent::endSpan($hasExitedByException, $returnValueOrThrown);
                        }
                    };
                }
            )
        );
    }
}
