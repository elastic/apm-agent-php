<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\BackendComm\SerializationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class SpanData extends ExecutionSegmentData
{
    /** @var string */
    public $parentId;

    /** @var string */
    public $transactionId;

    /** @var string|null */
    public $action = null;

    /** @var string|null */
    public $subtype = null;

    /** @var StacktraceFrame[]|null */
    public $stacktrace = null;

    /** @var SpanContextData|null */
    public $context = null;

    public function jsonSerialize()
    {
        $result = parent::jsonSerialize();

        SerializationUtil::addNameValue('parent_id', $this->parentId, /* ref */ $result);
        SerializationUtil::addNameValue('transaction_id', $this->transactionId, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('action', $this->action, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('subtype', $this->subtype, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('stacktrace', $this->stacktrace, /* ref */ $result);

        if (!is_null($this->context)) {
            SerializationUtil::addNameValueIfNotEmpty('context', $this->context->jsonSerialize(), /* ref */ $result);
        }

        return $result;
    }
}
