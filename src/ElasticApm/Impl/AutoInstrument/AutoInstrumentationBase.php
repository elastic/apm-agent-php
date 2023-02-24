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

    /** @var Logger */
    private $logger;

    public function __construct(Tracer $tracer)
    {
        $this->tracer = $tracer;
        $this->logger = $tracer->loggerFactory()->loggerForClass(
            LogCategory::AUTO_INSTRUMENTATION,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        );
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
     * @param mixed[] $interceptedCallArgs
     *
     * @return bool
     */
    protected function verifyAtLeastOneArgument(array $interceptedCallArgs): bool
    {
        if (count($interceptedCallArgs) < 1) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Number of received arguments for call is less than expected.');

            return false;
        }

        return true;
    }

    /**
     * @return string[]
     */
    protected static function propertiesExcludedFromLog(): array
    {
        return ['tracer'];
    }
}
