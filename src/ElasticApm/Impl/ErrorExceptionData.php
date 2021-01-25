<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\CustomErrorData;
use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use JsonSerializable;
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
class ErrorExceptionData implements JsonSerializable, LoggableInterface
{
    use LoggableTrait;

    /**
     * @var int|string|null
     *
     * The error code set when the error happened, e.g. database error code
     *
     * The length of a string value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L56
     */
    public $code = null;

    /**
     * @var string|null
     *
     * The original error message
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L61
     */
    public $message = null;

    /**
     * @var string|null
     *
     * Describes the exception type's module namespace
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L65
     */
    public $module = null;

    /**
     * @var StacktraceFrame[]|null
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L73
     */
    public $stacktrace = null;

    /**
     * @var string|null
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L80
     */
    public $type = null;

    public static function buildFromCustomData(Tracer $tracer, CustomErrorData $customErrorData): ErrorExceptionData
    {
        $result = new ErrorExceptionData();

        $result->code = is_string($customErrorData->code)
            ? Tracer::limitKeywordString($customErrorData->code)
            : $customErrorData->code;

        $result->message = $tracer->limitNullableNonKeywordString($customErrorData->message);
        $result->module = Tracer::limitNullableKeywordString($customErrorData->module);
        $result->type = Tracer::limitNullableKeywordString($customErrorData->type);

        return $result;
    }

    public static function buildFromThrowable(Tracer $tracer, Throwable $throwable): ErrorExceptionData
    {
        $customErrorData = new CustomErrorData();

        $code = $throwable->getCode();
        if (is_int($code) || is_string($code)) {
            $customErrorData->code = $code;
        }

        $message = $throwable->getMessage();
        if (is_string($message)) {
            $customErrorData->message = $message;
        }

        $namespace = '';
        $shortName = '';
        ClassNameUtil::splitFqClassName(get_class($throwable), /* ref */ $namespace, /* ref */ $shortName);
        $customErrorData->module = empty($namespace) ? null : $namespace;
        $customErrorData->type = empty($shortName) ? null : $shortName;

        $result = self::buildFromCustomData($tracer, $customErrorData);

        $result->stacktrace = StacktraceUtil::convertFromPhp($throwable->getTrace());

        return $result;
    }

    public function jsonSerialize()
    {
        $result = [];

        SerializationUtil::addNameValueIfNotNull('code', $this->code, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('message', $this->message, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('module', $this->module, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('stacktrace', $this->stacktrace, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('type', $this->type, /* ref */ $result);

        return $result;
    }
}
