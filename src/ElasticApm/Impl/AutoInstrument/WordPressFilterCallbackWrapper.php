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

    /** @var string */
    private $callbackGroupKind;

    /** @var ?string */
    private $callbackGroupName;

    /**
     * @param string  $hookName
     * @param mixed   $callback
     * @param string  $callbackGroupKind
     * @param ?string $callbackGroupName
     */
    public function __construct(string $hookName, $callback, string $callbackGroupKind, ?string $callbackGroupName)
    {
        $this->hookName = $hookName;
        $this->callback = $callback;
        $this->callbackGroupKind = $callbackGroupKind;
        $this->callbackGroupName = $callbackGroupName;
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
        $args = func_get_args();
        return ElasticApm::getCurrentTransaction()->captureCurrentSpan(
            $this->hookName . ' - ' . ($this->callbackGroupName ?? WordPressAutoInstrumentation::SPAN_NAME_PART_FOR_CORE) /* <- name */,
            $type = $this->callbackGroupKind /* <- type */,
            /**
             * @return mixed
             */
            function () use ($args) {
                return call_user_func_array($this->callback, $args); // @phpstan-ignore-line - $this->callback should have type callable
            },
            $this->callbackGroupName /* <- subtype */,
            $this->hookName /* <- action */
        );
    }
}
