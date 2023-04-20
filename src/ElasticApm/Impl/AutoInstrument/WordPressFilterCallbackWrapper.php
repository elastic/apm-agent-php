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

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class WordPressFilterCallbackWrapper implements LoggableInterface
{
    use LoggableTrait;

    /** @var string */
    private $hookName;

    /** @var mixed */
    private $callback;

    /** @var ?string */
    private $addonName;

    /**
     * @param string  $hookName
     * @param mixed   $callback
     * @param ?string $addonName
     */
    public function __construct(string $hookName, $callback, ?string $addonName)
    {
        $this->hookName = $hookName;
        $this->callback = $callback;
        $this->addonName = $addonName;
    }

    /**
     * @return mixed
     */
    public function getWrappedCallback()
    {
        return $this->callback;
    }

    /**
     * @return mixed
     */
    public function __invoke()
    {
        $name = $this->hookName . ' - ' . ($this->addonName ?? WordPressAutoInstrumentation::SPAN_NAME_PART_FOR_CORE);
        $type = $this->addonName === null ? WordPressAutoInstrumentation::SPAN_TYPE_FOR_CORE : WordPressAutoInstrumentation::SPAN_TYPE_FOR_ADDONS;
        $subtype = $this->addonName;
        $action = $this->hookName;
        $span = ElasticApm::getCurrentTransaction()->beginCurrentSpan($name, $type, $subtype, $action);

        try {
            return call_user_func_array($this->callback, func_get_args()); // @phpstan-ignore-line
        } catch (Throwable $throwable) {
            $span->createErrorFromThrowable($throwable);
            throw $throwable;
        } finally {
            $span->end();
        }
    }
}
