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
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Tracer;
use PDOStatement;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class PdoAutoInstrumentation implements LoggableInterface
{
    use AutoInstrumentationTrait;

    /** @var Tracer */
    private $tracer;

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
        )->addContext('this', $this);
    }

    public function register(RegistrationContextInterface $ctx): void
    {
        if (!extension_loaded('pdo')) {
            return;
        }

        // $this->pdoConstruct($ctx);
        $this->pdoExec($ctx);
        $this->pdoQuery($ctx);
        // $this->pdoPrepare($ctx);
        $this->pdoBeginTransaction($ctx);
        $this->pdoCommit($ctx);
        $this->pdoRollBack($ctx);
        $this->pdoStatementExecute($ctx);
    }

    private function interceptCallToSpan(
        RegistrationContextInterface $ctx,
        string $methodName,
        string $spanName = 'No name'
    ): void {
        $ctx->interceptCallsToMethod(
            'PDO',
            $methodName,
            /**
             * @param object|null $interceptedCallThis Intercepted call $this
             * @param mixed[]     $interceptedCallArgs Intercepted call arguments
             *
             * @return callable
             *
             */
            function (?object $interceptedCallThis, array $interceptedCallArgs) use ($spanName): ?callable {
                $statement = count($interceptedCallArgs) > 0 ? $interceptedCallArgs[0] : $spanName;
                $span = ElasticApm::getCurrentTransaction()->beginCurrentSpan(
                    $statement,
                    Constants::SPAN_TYPE_DB
                );
                $span->context()->db()->setStatement($statement);

                return self::createPostHookFromEndSpan($span);
            }
        );
    }

    // private function pdoConstruct(RegistrationContextInterface $ctx): void
    // {
    //     $this->interceptCallToSpan($ctx, '__construct');
    // }

    private function pdoExec(RegistrationContextInterface $ctx): void
    {
        $this->interceptCallToSpan($ctx, 'exec');
    }

    private function pdoQuery(RegistrationContextInterface $ctx): void
    {
        $this->interceptCallToSpan($ctx, 'query');
    }

    private function pdoBeginTransaction(RegistrationContextInterface $ctx): void
    {
         $this->interceptCallToSpan($ctx, 'beginTransaction', 'Begin Transaction');
    }

    private function pdoCommit(RegistrationContextInterface $ctx): void
    {
         $this->interceptCallToSpan($ctx, 'commit', 'Commit Transaction');
    }

    private function pdoRollBack(RegistrationContextInterface $ctx): void
    {
        $this->interceptCallToSpan($ctx, 'rollBack', 'Rollback Transaction');
    }

    // private function pdoPrepare(RegistrationContextInterface $ctx): void
    // {
    //     $this->interceptCallToSpan($ctx, 'prepare');
    // }

    private function pdoStatementExecute(RegistrationContextInterface $ctx): void
    {
        $ctx->interceptCallsToMethod(
            'PDOStatement',
            'execute',
            /**
             * @param object|null $interceptedCallThis Intercepted call $this
             * @param mixed[]     $interceptedCallArgs Intercepted call arguments
             *
             * @return callable
             *
             */
            function (
                ?object $interceptedCallThis,
                /** @noinspection PhpUnusedParameterInspection */ array $interceptedCallArgs
            ): ?callable {
                $spanName
                    = (
                    !is_null($interceptedCallThis)
                    && is_object($interceptedCallThis)
                    && $interceptedCallThis instanceof PDOStatement
                    && isset($interceptedCallThis->queryString)
                    && is_string($interceptedCallThis->queryString)
                )
                    ? $interceptedCallThis->queryString
                    : 'PDOStatement->execute';

                $span = ElasticApm::getCurrentTransaction()->beginCurrentSpan(
                    $spanName,
                    Constants::SPAN_TYPE_DB
                );

                return self::createPostHookFromEndSpan($span);
            }
        );
    }

    /**
     * @return string[]
     */
    protected static function propertiesExcludedFromLog(): array
    {
        return ['logger', 'tracer'];
    }
}
