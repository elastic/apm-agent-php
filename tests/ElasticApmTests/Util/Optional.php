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

namespace ElasticApmTests\Util;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use PHPUnit\Framework\TestCase;

/**
 * @template T
 */
final class Optional implements LoggableInterface
{
    use LoggableTrait;

    /** @var bool */
    public $isValueSet = false;

    /** @var T */
    private $value;

    /**
     * @return T
     */
    public function getValue()
    {
        TestCase::assertTrue($this->isValueSet);
        return $this->value;
    }

    /**
     * @param T $value
     */
    public function setValue($value): void
    {
        $this->value = $value;
        $this->isValueSet = true;
    }

    /**
     * @param T $elseValue
     *
     * @return T
     */
    public function getValueOr($elseValue)
    {
        return $this->isValueSet ? $this->value : $elseValue;
    }

    public function reset(): void
    {
        $this->isValueSet = false;
    }

    public function isValueSet(): bool
    {
        return $this->isValueSet;
    }

    /**
     * @param T $value
     */
    public function setValueIfNotSet($value): void
    {
        if (!$this->isValueSet) {
            $this->setValue($value);
        }
    }
}
