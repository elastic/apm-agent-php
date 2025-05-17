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

use Elastic\Apm\Impl\AutoInstrument\Util\AutoInstrumentationUtil;
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

    /** @var callable */
    private $callback;

    /** @var string */
    private $callbackGroupKind;

    /** @var ?string */
    private $callbackGroupName;

    /** @var int */
    public static $ctorCalls = 0;

    /** @var int */
    public static $dtorCalls = 0;

    /**
     * @param string  $hookName
     * @param mixed   $callback
     * @param string  $callbackGroupKind
     * @param ?string $callbackGroupName
     */
    public function __construct(string $hookName, $callback, string $callbackGroupKind, ?string $callbackGroupName)
    {
        ++self::$ctorCalls;
        $this->hookName = $hookName;
        /** @var callable $callback */
        $this->callback = $callback;
        $this->callbackGroupKind = $callbackGroupKind;
        $this->callbackGroupName = $callbackGroupName;
    }

    public function __destruct()
    {
        ++self::$dtorCalls;
    }

    /**
     * @return mixed
     * @noinspection PhpReturnDocTypeMismatchInspection
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
        if ( ! $this->shouldInstrument() ) {
            /** @phpstan-assert callable(mixed ...): mixed $this->callback */
            return call_user_func_array( $this->callback, func_get_args() );
        }

        /** @phpstan-assert callable(mixed ...): mixed $this->callback */
        return AutoInstrumentationUtil::captureCurrentSpan(
            $this->hookName . ' - ' . ($this->callbackGroupName ?? WordPressAutoInstrumentation::SPAN_NAME_PART_FOR_CORE) /* <- name */,
            $this->callbackGroupKind /* <- type */,
            $this->callbackGroupName /* <- subtype */,
            $this->hookName /* <- action */,
            $this->callback,
            func_get_args() /* <- callbackArgs */,
            1 /* <- numberOfStackFramesToSkip - 1 because we don't want the current method (i.e., WordPressFilterCallbackWrapper->__invoke) to be kept */
        );
    }

    private function shouldInstrument()
    {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            return true;
        }
    
        if ( null !== $this->callbackGroupName && WordPressAutoInstrumentation::SPAN_NAME_PART_FOR_CORE !== $this->callbackGroupName ) {
            return true;
        }
    
        return false;
    }

}
