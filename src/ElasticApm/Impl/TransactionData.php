<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\BackendComm\SerializationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class TransactionData extends ExecutionSegmentData
{
    /** @var string|null */
    public $parentId = null;

    /** @var int */
    public $startedSpansCount = 0;

    /** @var int */
    public $droppedSpansCount = 0;

    /** @var string|null */
    public $result = null;

    /** @var bool */
    public $isSampled = true;

    /** @var TransactionContextData|null */
    public $context = null;

    public function jsonSerialize()
    {
        $result = parent::jsonSerialize();

        SerializationUtil::addNameValueIfNotNull('parent_id', $this->parentId, /* ref */ $result);

        $spanCountSubObject = ['started' => $this->startedSpansCount];
        if ($this->droppedSpansCount != 0) {
            $spanCountSubObject['dropped'] = $this->droppedSpansCount;
        }
        SerializationUtil::addNameValue('span_count', $spanCountSubObject, /* ref */ $result);

        SerializationUtil::addNameValueIfNotNull('result', $this->result, /* ref */ $result);

        // https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L72
        // 'sampled' is optional and defaults to true.
        if (!$this->isSampled) {
            SerializationUtil::addNameValue('sampled', $this->isSampled, /* ref */ $result);
        }

        if (!is_null($this->context)) {
            SerializationUtil::addNameValueIfNotEmpty('context', $this->context->jsonSerialize(), /* ref */ $result);
        }

        return $result;
    }
}
