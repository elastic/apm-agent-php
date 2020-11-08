<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

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
    public $code;

    /**
     * @var string|null
     *
     * The original error message
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L61
     */
    public $message;

    /**
     * @var string|null
     *
     * Describes the exception type's module namespace
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L65
     */
    public $module;

    /**
     * @var StacktraceFrame[]|null
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L73
     */
    public $stacktrace;

    /**
     * @var string|null
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L80
     */
    public $type;

    public static function build(Tracer $tracer, Throwable $throwable): ErrorExceptionData
    {
        $result = new ErrorExceptionData();

        $result->code = $throwable->getCode();
        $result->message = $tracer->limitNonKeywordString($throwable->getMessage());

        $namespace = '';
        $shortName = '';
        ClassNameUtil::splitFqClassName(get_class($throwable), /* ref */ $namespace, /* ref */ $shortName);
        if (!empty($namespace)) {
            $result->module = Tracer::limitKeywordString($namespace);
        }
        if (!empty($shortName)) {
            $result->type = Tracer::limitKeywordString($shortName);
        }

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
