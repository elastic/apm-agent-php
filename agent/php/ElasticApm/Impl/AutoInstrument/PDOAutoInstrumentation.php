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
use Elastic\Apm\Impl\AutoInstrument\Util\MapPerWeakObject;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Tracer;
use PDO;
use PDOStatement;
use ReflectionClass;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class PDOAutoInstrumentation extends AutoInstrumentationBase
{
    private const PDO_CLASS_NAME = 'PDO';
    private const PDO_STATEMENT_CLASS_NAME = 'PDOStatement';

    private const PER_OBJECT_KEYS_TO_PROPAGATE = [
        DbAutoInstrumentationUtil::PER_OBJECT_KEY_DB_TYPE,
        DbAutoInstrumentationUtil::PER_OBJECT_KEY_DB_NAME,
    ];

    /** @var Logger */
    private $logger;

    /** @var AutoInstrumentationUtil */
    private $util;

    /** @var MapPerWeakObject */
    private $mapPerObject;

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
        $this->mapPerObject = MapPerWeakObject::create($tracer->loggerFactory());
        $this->dataSourceNameParser = new DbConnectionStringParser($tracer->loggerFactory());
    }

    /** @inheritDoc */
    public function requiresAttachContextToExternalObjects(): bool
    {
        return true;
    }

    /** @inheritDoc */
    public function name(): string
    {
        return InstrumentationNames::PDO;
    }

    /** @inheritDoc */
    public function keywords(): array
    {
        return [InstrumentationKeywords::DB];
    }

    /** @inheritDoc */
    public function register(RegistrationContextInterface $ctx): void
    {
        if (!extension_loaded('pdo')) {
            return;
        }

        foreach (array_merge([self::PDO_CLASS_NAME], self::findInternalPdoSubclasses()) as $pdoClassName) {
            $this->interceptPDOClass($ctx, $pdoClassName);
        }
        $this->interceptPDOStatementExecute($ctx);
    }

    /**
     * Returns the names of internal (i.e., provided by PHP extensions) classes that extend PDO.
     *
     * PHP 8.4 introduced driver specific subclasses of PDO (Pdo\Mysql, Pdo\Pgsql, Pdo\Sqlite, etc.)
     * returned by PDO::connect() and directly instantiable via `new Pdo\Pgsql(...)`.
     * @link https://wiki.php.net/rfc/pdo_driver_specific_subclasses
     *
     * When an internal class extends another internal class, the engine copies the parent's zend_function
     * for each inherited method into the subclass at the time the subclass is registered (see zend_duplicate_internal_function()
     * in Zend/zend_inheritance.c) - i.e., during extension's MINIT, way before the tracer intercepts calls to PDO's methods.
     * So the subclass' copies keep the original (not intercepted) handlers and have to be intercepted separately.
     *
     * Userland subclasses do not need special handling because they are linked when user code is executed,
     * i.e., after the tracer replaced PDO methods' handlers, so they inherit already intercepted handlers.
     *
     * @return string[]
     */
    public static function findInternalPdoSubclasses(): array
    {
        $result = [];
        foreach (get_declared_classes() as $className) {
            if (is_subclass_of($className, PDO::class) && (new ReflectionClass($className))->isInternal()) {
                $result[] = $className;
            }
        }
        return $result;
    }

    private function interceptPDOClass(RegistrationContextInterface $ctx, string $className): void
    {
        $this->interceptPDOConstruct($ctx, $className);
        // PDO::connect() was added in PHP 8.4
        if (method_exists($className, 'connect')) {
            $this->interceptPDOConnect($ctx, $className);
        }
        $this->interceptPDOExec($ctx, $className);
        $this->interceptPDOQuery($ctx, $className);
        $this->interceptPDOPrepare($ctx, $className);

        $this->interceptPDOMethodToSpanAsFuncCall($ctx, $className, 'beginTransaction');
        $this->interceptPDOMethodToSpanAsFuncCall($ctx, $className, 'commit');
        $this->interceptPDOMethodToSpanAsFuncCall($ctx, $className, 'rollBack');
    }

    private function storeDbInfoFromDsn(PDO $pdo, string $dsn): void
    {
        /** @var ?string $dbType */
        $dbType = null;
        /** @var ?string $dbName */
        $dbName = null;
        $this->dataSourceNameParser->parse($dsn, /* ref */ $dbType, /* ref */ $dbName);
        $mapToStoreForPdoObj = [];
        if ($dbType !== null) {
            $mapToStoreForPdoObj[DbAutoInstrumentationUtil::PER_OBJECT_KEY_DB_TYPE] = $dbType;
        }
        if ($dbName !== null) {
            $mapToStoreForPdoObj[DbAutoInstrumentationUtil::PER_OBJECT_KEY_DB_NAME] = $dbName;
        }
        $this->mapPerObject->setMultiple($pdo, $mapToStoreForPdoObj);
    }

    private function interceptPDOConstruct(RegistrationContextInterface $ctx, string $className): void
    {
        $ctx->interceptCallsToInternalMethod(
            $className,
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

                $this->storeDbInfoFromDsn($interceptedCallThis, $dsn);
                return null; // no post-hook
            }
        );
    }

    /**
     * PDO::connect() (added in PHP 8.4) is a static factory method that creates the PDO object
     * without calling PDO::__construct() so it has to be intercepted separately.
     */
    private function interceptPDOConnect(RegistrationContextInterface $ctx, string $className): void
    {
        $ctx->interceptCallsToInternalMethod(
            $className,
            'connect',
            /**
             * Pre-hook
             *
             * @param ?object $interceptedCallThis
             * @param mixed[] $interceptedCallArgs
             *
             * @return null|callable(int, bool, mixed): void
             */
            function (
                /** @noinspection PhpUnusedParameterInspection */ ?object $interceptedCallThis,
                array $interceptedCallArgs
            ): ?callable {
                if (!$this->util->verifyMinArgsCount(1, $interceptedCallArgs)) {
                    return null;
                }
                $dsn = $interceptedCallArgs[0];
                if (!$this->util->verifyIsString($dsn)) {
                    return null;
                }
                /** @var string $dsn */

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
                    $dsn
                ): void {
                    if ($hasExitedByException) {
                        return;
                    }

                    if (!$this->util->verifyInstanceOf(PDO::class, $returnValueOrThrown)) {
                        return;
                    }
                    /** @var PDO $returnValueOrThrown */

                    $this->storeDbInfoFromDsn($returnValueOrThrown, $dsn);
                };
            }
        );
    }

    private function interceptPDOMethodToSpan(RegistrationContextInterface $ctx, string $className, string $methodName, bool $isFirstArgStatement): void
    {
        $ctx->interceptCallsToInternalMethod(
            $className,
            $methodName,
            /**
             * @param ?object $interceptedCallThis
             * @param mixed[] $interceptedCallArgs
             *
             * @return callable
             */
            function (?object $interceptedCallThis, array $interceptedCallArgs) use ($methodName, $isFirstArgStatement): ?callable {
                if (!$this->util->verifyInstanceOf(PDO::class, $interceptedCallThis)) {
                    return null;
                }
                /** @var PDO $interceptedCallThis */

                $statement = null;
                if ($isFirstArgStatement) {
                    if ($this->util->verifyMinArgsCount(1, $interceptedCallArgs) && $this->util->verifyIsString($interceptedCallArgs[0])) {
                        $statement = $interceptedCallArgs[0];
                    }
                }
                /** @var ?string $statement */

                /** @var string $dbType */
                $dbType = $this->mapPerObject->getOr($interceptedCallThis, DbAutoInstrumentationUtil::PER_OBJECT_KEY_DB_TYPE, /* defaultValue */ Constants::SPAN_SUBTYPE_UNKNOWN);
                /** @var ?string $dbName */
                $dbName = $this->mapPerObject->getOr($interceptedCallThis, DbAutoInstrumentationUtil::PER_OBJECT_KEY_DB_NAME, /* defaultValue */ null);
                // Span name uses `PDO' as class name even for PDO subclasses (for example Pdo\Pgsql)
                // so span names do not depend on how the PDO object was created
                return AutoInstrumentationUtil::createInternalFuncPostHookFromEndSpan(DbAutoInstrumentationUtil::beginDbSpan(self::PDO_CLASS_NAME, $methodName, $dbType, $dbName, $statement));
            }
        );
    }

    private function interceptPDOExec(RegistrationContextInterface $ctx, string $className): void
    {
        $this->interceptPDOMethodToSpan($ctx, $className, 'exec', /* isFirstArgStatement */ true);
    }

    private function interceptPDOQuery(RegistrationContextInterface $ctx, string $className): void
    {
        $this->interceptPDOMethodToSpan($ctx, $className, 'query', /* isFirstArgStatement */ true);
    }

    private function interceptPDOMethodToSpanAsFuncCall(RegistrationContextInterface $ctx, string $className, string $methodName): void
    {
        $this->interceptPDOMethodToSpan($ctx, $className, $methodName, /* isFirstArgStatement */ false);
    }

    private function interceptPDOPrepare(RegistrationContextInterface $ctx, string $className): void
    {
        $ctx->interceptCallsToInternalMethod(
            $className,
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

                $keyValueMapPerObjectToPropagate = $this->mapPerObject->getMultiple(
                    $interceptedCallThis,
                    self::PER_OBJECT_KEYS_TO_PROPAGATE
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
                    $keyValueMapPerObjectToPropagate
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

                    $this->mapPerObject->setMultiple($returnValueOrThrown, $keyValueMapPerObjectToPropagate);
                };
            }
        );
    }

    private function interceptPDOStatementExecute(RegistrationContextInterface $ctx): void
    {
        $className = self::PDO_STATEMENT_CLASS_NAME;
        $methodName = 'execute';
        $ctx->interceptCallsToInternalMethod(
            $className,
            $methodName,
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
            ) use (
                $className,
                $methodName
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
                $dbType = $this->mapPerObject->getOr(
                    $interceptedCallThis,
                    DbAutoInstrumentationUtil::PER_OBJECT_KEY_DB_TYPE,
                    Constants::SPAN_SUBTYPE_UNKNOWN /* <- defaultValue */
                );
                /** @var ?string $dbName */
                $dbName = $this->mapPerObject->getOr(
                    $interceptedCallThis,
                    DbAutoInstrumentationUtil::PER_OBJECT_KEY_DB_NAME,
                    null /* <- defaultValue */
                );
                return AutoInstrumentationUtil::createInternalFuncPostHookFromEndSpan(
                    DbAutoInstrumentationUtil::beginDbSpan(
                        $className,
                        $methodName,
                        $dbType,
                        $dbName,
                        $statement
                    )
                );
            }
        );
    }
}
