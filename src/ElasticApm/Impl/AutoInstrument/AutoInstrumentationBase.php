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

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\Impl\AutoInstrument\Util\MapPerWeakObject;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Tracer;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class AutoInstrumentationBase implements AutoInstrumentationInterface, LoggableInterface
{
    use LoggableTrait;

    /** @var Tracer */
    protected $tracer;

    public function __construct(Tracer $tracer)
    {
        $this->tracer = $tracer;
    }

    /** @inheritDoc */
    public function isEnabled(?string &$reason = null): bool
    {
        if ($this->requiresAttachContextToExternalObjects() && !MapPerWeakObject::isSupported()) {
            $reason = 'Instrumentation ' . $this->name() . ' needs to attach context to external objects'
                      . ' but none of the MapPerWeakObject implementations is supported by the current environment';
            return false;
        }

        $isUserlandCodeInstrumentationEnabled = $this->tracer->getConfig()->astProcessEnabled();
        if ($this->requiresUserlandCodeInstrumentation() && (!$isUserlandCodeInstrumentationEnabled)) {
            $reason = 'Instrumentation ' . $this->name() . ' needs userland code instrumentation'
                      . ' but AST-process is the only currently supported mechanism to instrument userland code and it is DISABLED'
                      . ' (via ' . OptionNames::AST_PROCESS_ENABLED . ' configuration option)';
            return false;
        }

        $disabledInstrumentationsMatcher = $this->tracer->getConfig()->disableInstrumentations();
        if ($disabledInstrumentationsMatcher === null) {
            return true;
        }

        if ($disabledInstrumentationsMatcher->match($this->name()) !== null) {
            $reason = 'name (`' . $this->name() . '\') is matched by '
                      . OptionNames::DISABLE_INSTRUMENTATIONS . ' configuration option';
            return false;
        }

        foreach ($this->keywords() as $keyword) {
            if ($disabledInstrumentationsMatcher->match($keyword) !== null) {
                $reason = 'one of keywords (`' . $keyword . '\') is matched by '
                          . OptionNames::DISABLE_INSTRUMENTATIONS . ' configuration option';
                return false;
            }
        }

        return true;
    }

    /**
     * @return bool
     */
    public function requiresAttachContextToExternalObjects(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    public function requiresUserlandCodeInstrumentation(): bool
    {
        return false;
    }

    /**
     * @return string[]
     */
    protected static function propertiesExcludedFromLog(): array
    {
        return ['tracer'];
    }
}
