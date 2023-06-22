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

namespace Elastic\Apm\Impl;

use Elastic\Apm\CustomErrorData;
use Elastic\Apm\Impl\AutoInstrument\PhpErrorData;
use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Util\BoolUtil;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\TextUtil;
use Throwable;

/**
 * Information about the originally thrown error
 *
 * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L53
 *
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class ErrorExceptionData implements OptionalSerializableDataInterface, LoggableInterface
{
    use LoggableTrait;

    /**
     * @var null|int|string
     *
     * The error code set when the error happened, e.g. database error code
     *
     * The length of a string value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L56
     */
    public $code = null;

    /**
     * @var ?string
     *
     * The original error message
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L61
     */
    public $message = null;

    /**
     * @var ?string
     *
     * Describes the exception type's module namespace
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L65
     */
    public $module = null;

    /**
     * @var null|StackTraceFrame[]
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L73
     */
    public $stacktrace = null;

    /**
     * @var ?string
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L80
     */
    public $type = null;

    /**
     * @param Tracer           $tracer
     * @param ?CustomErrorData $customErrorData
     * @param ?PhpErrorData    $phpErrorData
     * @param ?Throwable       $throwable
     * @param int              $numberOfStackFramesToSkip
     *
     * @return ErrorExceptionData
     *
     * @phpstan-param 0|positive-int $numberOfStackFramesToSkip
     */
    public static function build(Tracer $tracer, ?CustomErrorData $customErrorData, ?PhpErrorData $phpErrorData, ?Throwable $throwable, int $numberOfStackFramesToSkip): ErrorExceptionData
    {
        $result = new ErrorExceptionData();

        if ($throwable !== null) {
            $code = $throwable->getCode();
            if (is_int($code)) {
                $result->code = $code;
            } elseif (is_string($code)) {
                $result->code = Tracer::limitKeywordString($code);
            }

            $message = $throwable->getMessage();
            if (is_string($message)) {
                $result->message = $tracer->limitNullableNonKeywordString($message);
            }

            $namespace = '';
            $shortName = '';
            ClassNameUtil::splitFqClassName(get_class($throwable), /* ref */ $namespace, /* ref */ $shortName);
            $result->module = TextUtil::isEmptyString($namespace) ? null : $namespace;
            $result->type = TextUtil::isEmptyString($shortName) ? null : $shortName;

            $result->stacktrace = $tracer->stackTraceUtil()->convertThrowableTraceToApmFormat($throwable, /* maxNumberOfFrames */ null);
        }

        if ($customErrorData !== null) {
            if ($result->code === null) {
                $result->code = is_string($customErrorData->code) ? Tracer::limitKeywordString($customErrorData->code) : $customErrorData->code;
            }

            if ($result->message === null) {
                $result->message = $tracer->limitNullableNonKeywordString($customErrorData->message);
            }

            if ($result->module === null) {
                $result->module = Tracer::limitNullableKeywordString($customErrorData->module);
            }

            if ($result->type === null) {
                $result->type = Tracer::limitNullableKeywordString($customErrorData->type);
            }
        }

        if ($result->stacktrace === null) {
            if ($phpErrorData === null) {
                $result->stacktrace = $tracer->stackTraceUtil()->captureInApmFormat($numberOfStackFramesToSkip, /* maxNumberOfFrames */ null);
            } elseif ($phpErrorData->stackTrace !== null) {
                $result->stacktrace = $tracer->stackTraceUtil()->convertPhpToApmFormat($phpErrorData->stackTrace, /* maxNumberOfFrames */ null);
            }
        }

        return $result;
    }

    /** @inheritDoc */
    public function prepareForSerialization(): int
    {
        return BoolUtil::toInt(
            $this->code !== null
            || $this->message !== null
            || $this->module !== null
            || $this->stacktrace !== null
            || $this->type !== null
        );
    }

    /** @inheritDoc */
    public function jsonSerialize()
    {
        $result = [];

        SerializationUtil::addNameValueIfNotNull('code', $this->code, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('message', $this->message, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('module', $this->module, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('stacktrace', $this->stacktrace, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('type', $this->type, /* ref */ $result);

        return SerializationUtil::postProcessResult($result);
    }
}
