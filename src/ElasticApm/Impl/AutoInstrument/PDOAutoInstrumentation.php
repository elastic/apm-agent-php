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
use Elastic\Apm\Impl\AutoInstrument\Util\DbAutoInstrumentationUtil;
use Elastic\Apm\Impl\AutoInstrument\Util\DbConnectionStringParser;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Tracer;
use PDO;
use PDOStatement;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class PDOAutoInstrumentation extends AutoInstrumentationBase
{
    private const DYNAMICALLY_ATTACHED_PROPERTIES_TO_PROPAGATE = [
        DbAutoInstrumentationUtil::DYNAMICALLY_ATTACHED_PROPERTY_KEY_DB_TYPE,
        DbAutoInstrumentationUtil::DYNAMICALLY_ATTACHED_PROPERTY_KEY_DB_NAME,
    ];

    /** @var Logger */
    private $logger;

    /** @var AutoInstrumentationUtil */
    private $util;

    /** @var DbConnectionStringParser */
    private $dataSourceNameParser;

    public function __construct(Tracer $tracer)
    {
        parent::__construct($tracer);

        $this->logger = $tracer->loggerFactory()->loggerForClass(
            LogCategory::AUTO_INSTRUMENTATION,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);

        $this->util = new AutoInstrumentationUtil($tracer->loggerFactory());

        $this->dataSourceNameParser = new DbConnectionStringParser($tracer->loggerFactory());
    }

    /** @inheritDoc */
    public function name(): string
    {
        return InstrumentationNames::PDO;
    }

    /** @inheritDoc */
    public function otherNames(): array
    {
        return [InstrumentationNames::DB];
    }

    /** @inheritDoc */
    public function register(RegistrationContextInterface $ctx): void
    {
        if (!extension_loaded('pdo')) {
            return;
        }

        $this->pdoConstruct($ctx);
        $this->pdoExec($ctx);
        $this->pdoQuery($ctx);
        $this->pdoPrepare($ctx);
        // $this->pdoCommit($ctx);
        $this->pdoStatementExecute($ctx);
    }

    private function pdoConstruct(RegistrationContextInterface $ctx): void
    {
        $ctx->interceptCallsToMethod(
            'PDO',
            '__construct',
            /**
             * @param ?object $interceptedCallThis
             * @param mixed[] $interceptedCallArgs
             *
             * @return callable
             */
            function (?object $interceptedCallThis, array $interceptedCallArgs): ?callable {
                if (!$this->util->verifyInstanceOf(PDO::class, $interceptedCallThis)) {
                    return null;
                }
                /** @var PDO $interceptedCallThis */
                if (!$this->util->verifyMinArgsCount(1, $interceptedCallArgs)) {
                    return null;
                }
                $dsn = $interceptedCallArgs[0];
                if (!$this->util->verifyIsString($dsn)) {
                    return null;
                }
                /** @var string $dsn */

                /** @var ?string $dbType */
                $dbType = null;
                /** @var ?string $dbName */
                $dbName = null;
                $this->dataSourceNameParser->parse($dsn, /* ref */ $dbType, /* ref */ $dbName);
                $dynamicallyAttachedProperties = [];
                if ($dbType !== null) {
                    $dynamicallyAttachedProperties[DbAutoInstrumentationUtil::DYNAMICALLY_ATTACHED_PROPERTY_KEY_DB_TYPE]
                        = $dbType;
                }
                if ($dbName !== null) {
                    $dynamicallyAttachedProperties[DbAutoInstrumentationUtil::DYNAMICALLY_ATTACHED_PROPERTY_KEY_DB_NAME]
                        = $dbName;
                }
                $this->util->setDynamicallyAttachedProperties(
                    $interceptedCallThis,
                    $dynamicallyAttachedProperties
                );
                return null; // no post-hook
            }
        );
    }

    private function pdoInterceptCallToSpan(RegistrationContextInterface $ctx, string $methodName): void
    {
        $ctx->interceptCallsToMethod(
            'PDO',
            $methodName,
            /**
             * @param ?object $interceptedCallThis
             * @param mixed[] $interceptedCallArgs
             *
             * @return callable
             */
            function (?object $interceptedCallThis, array $interceptedCallArgs) use ($methodName): ?callable {
                if (!$this->util->verifyInstanceOf(PDO::class, $interceptedCallThis)) {
                    return null;
                }
                /** @var PDO $interceptedCallThis */

                if (count($interceptedCallArgs) > 0) {
                    $statement = $interceptedCallArgs[0];
                    if (!is_string($statement)) {
                        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                        && $loggerProxy->log(
                            'The first received argument for PDO::' . $methodName . ' call is not a string'
                            . ' so statement cannot be captured',
                            ['interceptedCallThis' => $interceptedCallThis]
                        );
                        $statement = null;
                    }
                } else {
                    $statement = null;
                }
                /** @var ?string $statement */

                /** @var string $dbType */
                $dbType = $this->util->getDynamicallyAttachedProperty(
                    $interceptedCallThis,
                    DbAutoInstrumentationUtil::DYNAMICALLY_ATTACHED_PROPERTY_KEY_DB_TYPE,
                    Constants::SPAN_SUBTYPE_UNKNOWN /* <- defaultValue */
                );

                /** @var ?string $dbName */
                $dbName = $this->util->getDynamicallyAttachedProperty(
                    $interceptedCallThis,
                    DbAutoInstrumentationUtil::DYNAMICALLY_ATTACHED_PROPERTY_KEY_DB_NAME,
                    null /* <- defaultValue */
                );

                return AutoInstrumentationUtil::createPostHookFromEndSpan(
                    DbAutoInstrumentationUtil::beginDbSpan(
                        'PDO' /* <- className */,
                        $methodName,
                        $dbType,
                        $dbName,
                        $statement
                    )
                );
            }
        );
    }

    private function pdoExec(RegistrationContextInterface $ctx): void
    {
        $this->pdoInterceptCallToSpan($ctx, 'exec');
    }

    private function pdoQuery(RegistrationContextInterface $ctx): void
    {
        $this->pdoInterceptCallToSpan($ctx, 'query');
    }

    // private function pdoCommit(RegistrationContextInterface $ctx): void
    // {
    //     $this->interceptCallToSpan($ctx, 'commit');
    // }


    private function pdoPrepare(RegistrationContextInterface $ctx): void
    {
        $ctx->interceptCallsToMethod(
            'PDO',
            'prepare',
            /**
             * Pre-hook
             *
             * @param ?object $interceptedCallThis
             * @param mixed[] $interceptedCallArgs
             *
             * @return null|callable(int, bool, mixed): void
             */
            function (
                ?object $interceptedCallThis,
                /** @noinspection PhpUnusedParameterInspection */ array $interceptedCallArgs
            ): ?callable {
                if (!$this->util->verifyInstanceOf(PDO::class, $interceptedCallThis)) {
                    return null;
                }
                /** @var PDO $interceptedCallThis */

                $dynPropsToPropagate = $this->util->getDynamicallyAttachedProperties(
                    $interceptedCallThis,
                    self::DYNAMICALLY_ATTACHED_PROPERTIES_TO_PROPAGATE
                );

                /**
                 * Post-hook
                 *
                 * @param int   $numberOfStackFramesToSkip
                 * @param bool  $hasExitedByException
                 * @param mixed $returnValueOrThrown Return value of the intercepted call or thrown object
                 */
                return function (
                    int $numberOfStackFramesToSkip,
                    bool $hasExitedByException,
                    $returnValueOrThrown
                ) use (
                    $dynPropsToPropagate
                ): void {
                    if ($hasExitedByException || $returnValueOrThrown === false) {
                        return;
                    }

                    if (!($returnValueOrThrown instanceof PDOStatement)) {
                        ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                        && $loggerProxy->log(
                            'returnValueOrThrown is not an instance of class PDOStatement',
                            ['returnValueOrThrown' => $returnValueOrThrown]
                        );
                        return;
                    }

                    $this->util->setDynamicallyAttachedProperties(
                        $returnValueOrThrown,
                        $dynPropsToPropagate
                    );
                };
            }
        );
    }

    private function pdoStatementExecute(RegistrationContextInterface $ctx): void
    {
        $ctx->interceptCallsToMethod(
            'PDOStatement',
            'execute',
            /**
             * @param ?object $interceptedCallThis
             * @param mixed[]     $interceptedCallArgs
             *
             * @return callable
             *
             */
            function (
                ?object $interceptedCallThis,
                /** @noinspection PhpUnusedParameterInspection */ array $interceptedCallArgs
            ): ?callable {
                if (!$this->util->verifyInstanceOf(PDOStatement::class, $interceptedCallThis)) {
                    return null;
                }
                /** @var PDOStatement $interceptedCallThis */

                $statement = (
                    isset($interceptedCallThis->queryString)
                    && $this->util->verifyIsString($interceptedCallThis->queryString)
                )
                    ? $interceptedCallThis->queryString
                    : null;

                /** @var string $dbType */
                $dbType = $this->util->getDynamicallyAttachedProperty(
                    $interceptedCallThis,
                    DbAutoInstrumentationUtil::DYNAMICALLY_ATTACHED_PROPERTY_KEY_DB_TYPE,
                    Constants::SPAN_SUBTYPE_UNKNOWN /* <- defaultValue */
                );

                /** @var ?string $dbName */
                $dbName = $this->util->getDynamicallyAttachedProperty(
                    $interceptedCallThis,
                    DbAutoInstrumentationUtil::DYNAMICALLY_ATTACHED_PROPERTY_KEY_DB_NAME,
                    null /* <- defaultValue */
                );

                return AutoInstrumentationUtil::createPostHookFromEndSpan(
                    DbAutoInstrumentationUtil::beginDbSpan(
                        'PDOStatement' /* <- className */,
                        'execute' /* <- methodName */,
                        $dbType,
                        $dbName,
                        $statement
                    )
                );
            }
        );
    }
}
