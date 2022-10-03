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

namespace Elastic\Apm\Impl\Log;

use ReflectionClass;
use ReflectionException;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
trait LoggableTrait
{
    protected static function classNameToLog(): ?string
    {
        return null;
    }

    /**
     * @return string[]
     */
    protected static function defaultPropertiesExcludedFromLog(): array
    {
        return ['logger'];
    }

    /**
     * @return string[]
     */
    protected static function propertiesExcludedFromLog(): array
    {
        return [];
    }

    /**
     * @param LogStreamInterface   $stream
     * @param array<string, mixed> $customPropValues
     */
    protected function toLogLoggableTraitImpl(LogStreamInterface $stream, array $customPropValues = []): void
    {
        $nameToValue = $customPropValues;

        $classNameToLog = static::classNameToLog();
        if ($classNameToLog !== null) {
            $nameToValue[LogConsts::TYPE_KEY] = $classNameToLog;
        }

        try {
            $currentClass = new ReflectionClass(get_class($this));
        } /** @noinspection PhpRedundantCatchClauseInspection */ catch (ReflectionException $ex) {
            $stream->toLogAs(
                LoggingSubsystem::onInternalFailure('Failed to reflect', ['class' => get_class($this)], $ex)
            );
            return;
        }

        $propertiesExcludedFromLog = array_merge(
            static::propertiesExcludedFromLog(),
            static::defaultPropertiesExcludedFromLog()
        );
        while (true) {
            foreach ($currentClass->getProperties() as $reflectionProperty) {
                if ($reflectionProperty->isStatic()) {
                    continue;
                }

                $reflectionProperty->setAccessible(true);
                $propName = $reflectionProperty->name;
                if (array_key_exists($propName, $customPropValues)) {
                    continue;
                }
                if (in_array($propName, $propertiesExcludedFromLog, /* strict */ true)) {
                    continue;
                }
                $nameToValue[$propName] = $reflectionProperty->getValue($this);
            }
            $currentClass = $currentClass->getParentClass();
            if ($currentClass === false) {
                break;
            }
        }

        $stream->toLogAs($nameToValue);
    }

    public function toLog(LogStreamInterface $stream): void
    {
        $this->toLogLoggableTraitImpl($stream);
    }
}
