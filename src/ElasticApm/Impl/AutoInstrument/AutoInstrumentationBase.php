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

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Tracer;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class AutoInstrumentationBase implements AutoInstrumentationInterface, LoggableInterface
{
    use AutoInstrumentationTrait;

    /** @var Tracer */
    protected $tracer;

    public function __construct(Tracer $tracer)
    {
        $this->tracer = $tracer;
    }

    /** @inheritDoc */
    public function isEnabled(): bool
    {
        $disabledInstrumentationsMatcher = $this->tracer->getConfig()->disableInstrumentations();
        if ($disabledInstrumentationsMatcher === null) {
            return true;
        }

        if ($disabledInstrumentationsMatcher->match($this->name()) !== null) {
            return false;
        }

        foreach ($this->otherNames() as $otherName) {
            if ($disabledInstrumentationsMatcher->match($otherName) !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param object $obj
     * @param string $propName
     * @param mixed  $val
     */
    protected static function setDynamicallyAttachedProperty(object $obj, string $propName, $val): void
    {
        $obj->{$propName} = $val;
    }

    /**
     * @param object               $obj
     * @param array<string, mixed> $propNameVals
     */
    protected static function setDynamicallyAttachedProperties(object $obj, array $propNameVals): void
    {
        foreach ($propNameVals as $propName => $propVal) {
            self::setDynamicallyAttachedProperty($obj, $propName, $propVal);
        }
    }

    /**
     * @param ?object $obj
     * @param string  $propName
     * @param mixed   $defaultValue
     *
     * @return mixed
     */
    protected static function getDynamicallyAttachedProperty(?object $obj, string $propName, $defaultValue)
    {
        return ($obj !== null) && isset($obj->{$propName}) ? $obj->{$propName} : $defaultValue;
    }

    /**
     * @param ?object  $obj
     * @param string[] $propNames
     *
     * @return array<string, mixed>
     */
    protected static function getDynamicallyAttachedProperties(?object $obj, array $propNames): array
    {
        if ($obj === null) {
            return [];
        }

        $propNameVals = [];
        foreach ($propNames as $propName) {
            if (isset($obj->{$propName})) {
                $propNameVals[$propName] = $obj->{$propName};
            }
        }
        return $propNameVals;
    }

    /**
     * @return string[]
     */
    protected static function propertiesExcludedFromLog(): array
    {
        return ['tracer'];
    }
}
