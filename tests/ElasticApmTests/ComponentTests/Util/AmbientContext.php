<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Config\RawSnapshotSourceInterface;
use Elastic\Apm\Impl\Log\Backend as LogBackend;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Tests\Util\TestLogSink;
use RuntimeException;

final class AmbientContext
{
    /** @var self */
    private static $singletonInstance;

    /** @var string */
    private $dbgProcessName;

    /** @var LoggerFactory */
    private $loggerFactory;

    /** @var array<string> */
    private $testOptionNames;

    /** @var TestConfigSnapshot */
    private $testConfig;

    private function __construct(string $dbgProcessName)
    {
        $this->dbgProcessName = $dbgProcessName;
        $this->testOptionNames = array_keys(AllComponentTestsOptionsMetadata::build());
        $this->readAndApplyConfig(/* additionalConfigSource */ null);
    }

    public static function init(string $dbgProcessName): void
    {
        if (!isset(self::$singletonInstance)) {
            self::$singletonInstance = new AmbientContext($dbgProcessName);
        }

        if (self::config()->appCodeHostKind === AppCodeHostKind::NOT_SET) {
            $envVarName = TestConfigUtil::envVarNameForTestOption(AppCodeHostKindOptionMetadata::NAME);
            throw new RuntimeException(
                'Required configuration option ' . AppCodeHostKindOptionMetadata::NAME
                . " (environment variable $envVarName)" . ' is not set'
            );
        }
    }

    public static function reconfigure(RawSnapshotSourceInterface $additionalConfigSource): void
    {
        assert(isset(self::$singletonInstance));
        self::$singletonInstance->readAndApplyConfig($additionalConfigSource);
    }

    private function readAndApplyConfig(?RawSnapshotSourceInterface $additionalConfigSource): void
    {
        $this->testConfig = TestConfigUtil::read($this->dbgProcessName, $additionalConfigSource);
        $this->loggerFactory = new LoggerFactory(
            new LogBackend(
                $this->testConfig->logLevel,
                new TestLogSink($this->dbgProcessName)
            )
        );
    }

    public static function dbgProcessName(): string
    {
        assert(isset(self::$singletonInstance));

        return self::$singletonInstance->dbgProcessName;
    }

    public static function config(): TestConfigSnapshot
    {
        assert(isset(self::$singletonInstance));

        return self::$singletonInstance->testConfig;
    }

    public static function loggerFactory(): LoggerFactory
    {
        assert(isset(self::$singletonInstance));

        return self::$singletonInstance->loggerFactory;
    }
}
