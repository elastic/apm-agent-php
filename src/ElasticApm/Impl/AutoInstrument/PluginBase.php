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

use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Tracer;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class PluginBase implements PluginInterface
{
    /** @var AutoInstrumentationInterface[] */
    private $enabledInstrumentations;

    /**
     * @param Tracer $tracer
     * @param AutoInstrumentationInterface[]  $allInstrumentations
     */
    public function __construct(Tracer $tracer, array $allInstrumentations)
    {
        $logger = $tracer->loggerFactory()->loggerForClass(
            LogCategory::AUTO_INSTRUMENTATION,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        );

        $this->enabledInstrumentations = [];
        foreach ($allInstrumentations as $instr) {
            $isEnabled = $instr->isEnabled();
            ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Instrumentation ' . $instr->name() . ' is ' . ($isEnabled ?  'enabled' : 'disabled'),
                ['instrumentation other names' => $instr->otherNames()]
            );
            if ($isEnabled) {
                $this->enabledInstrumentations[] = $instr;
            }
        }
    }

    public function register(RegistrationContextInterface $ctx): void
    {
        foreach ($this->enabledInstrumentations as $instr) {
            $instr->register($ctx);
        }
    }
}
