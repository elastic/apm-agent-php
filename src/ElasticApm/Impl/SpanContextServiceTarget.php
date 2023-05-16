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

use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\Impl\Util\BoolUtil;
use Elastic\Apm\SpanContextServiceTargetInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends ContextPartWrapper<Span>
 */
final class SpanContextServiceTarget extends ContextPartWrapper implements SpanContextServiceTargetInterface
{
    /** @var ?string */
    public $name;

    /** @var ?string */
    public $type;

    public function __construct(Span $owner)
    {
        parent::__construct($owner);
    }

    /** @inheritDoc */
    public function setName(?string $name): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->name = Tracer::limitNullableKeywordString($name);
    }

    /** @inheritDoc */
    public function setType(?string $type): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->type = Tracer::limitNullableKeywordString($type);
    }

    /**
     * @param self $other
     *
     * @return bool
     */
    public function isEqualTo(SpanContextServiceTarget $other): bool
    {
        return $this->name === $other->name && $this->type === $other->type;
    }

    /**
     * @param ?self $lhs
     * @param ?self $rhs
     *
     * @return bool
     */
    public static function areNullableEqual(?SpanContextServiceTarget $lhs, ?SpanContextServiceTarget $rhs): bool
    {
        if (($lhs === null) !== ($rhs === null)) {
            return false;
        }

        if ($lhs === null) {
            return true;
        }

        /** @var SpanContextServiceTarget $rhs */
        return $lhs->isEqualTo($rhs);
    }

    /** @inheritDoc */
    public function prepareForSerialization(): int
    {
        return BoolUtil::toInt(($this->name !== null) || ($this->type !== null));
    }

    /** @inheritDoc */
    public function jsonSerialize()
    {
        $result = [];

        SerializationUtil::addNameValueIfNotNull('name', $this->name, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('type', $this->type, /* ref */ $result);

        return SerializationUtil::postProcessResult($result);
    }
}
