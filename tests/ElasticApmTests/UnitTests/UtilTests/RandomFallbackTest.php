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

namespace ElasticApmTests\UnitTests\UtilTests;

use PHPUnit\Framework\TestCase;

/**
 * Tests the fallback to mt_rand() in IdGenerator and RandomUtil used when random_bytes()/random_int() are not
 * available (turned off by disable_functions) or throw. Each case runs in a separate PHP process so that it does not
 * influence other tests and so that the fallback (which intentionally depends on the global mt_rand() state) is
 * isolated from the tests of the main code path.
 */
class RandomFallbackTest extends TestCase
{
    private const FLOATS_COUNT = 200;

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public function dataProviderForTestFallback(): iterable
    {
        yield 'random_bytes() and random_int() are turned off by disable_functions' => [
            '-d disable_functions=random_bytes,random_int',
            '',
            false,
        ];

        // Functions declared in the namespace of the agent code take precedence over the global ones.
        // random_bytes()/random_int() throw when there is no OS entropy source (for example getrandom() is blocked)
        yield 'random_bytes() and random_int() throw' => [
            '',
            <<<'PHP'
namespace Elastic\Apm\Impl\Util {
    function random_bytes(int $length): string
    {
        $GLOBALS['randomFunctionsCalls']++;
        throw new \Exception('no entropy source');
    }

    function random_int(int $min, int $max): int
    {
        $GLOBALS['randomFunctionsCalls']++;
        throw new \Exception('no entropy source');
    }
}
PHP
            ,
            true,
        ];
    }

    /**
     * @dataProvider dataProviderForTestFallback
     */
    public function testFallback(string $phpOptions, string $preamble, bool $expectRandomFunctionsCalled): void
    {
        $result = self::runChildPhpProcess($phpOptions, $preamble);

        // Make sure that the scenario really exercises the fallback
        self::assertSame($expectRandomFunctionsCalled, $result['randomFunctionsCalls'] > 0);
        self::assertSame([], $result['warnings'], 'No warnings should reach the application error handler');
        self::assertSame(1, preg_match('/^[0-9a-f]{16}$/', $result['id']), $result['id']);

        self::assertCount(self::FLOATS_COUNT, $result['floats']);
        foreach ($result['floats'] as $value) {
            self::assertGreaterThanOrEqual(0.0, $value);
            self::assertLessThanOrEqual(1.0, $value);
        }
        // mt_rand() is seeded randomly in a fresh process so the values should differ
        // (the result when a disabled function returns null is always 0.0)
        self::assertGreaterThan(self::FLOATS_COUNT / 2, count(array_unique($result['floats'])));
    }

    /**
     * @return array{id: string, floats: array<float>, warnings: array<string>, randomFunctionsCalls: int}
     */
    private static function runChildPhpProcess(string $phpOptions, string $preamble): array
    {
        $autoloadFile = dirname(__DIR__, 4) . '/vendor/autoload.php';
        $floatsCount = self::FLOATS_COUNT;
        $script = <<<PHP
<?php
{$preamble}
namespace {
    \$randomFunctionsCalls = 0;
    require '{$autoloadFile}';
    \$warnings = [];
    set_error_handler(function (int \$errno, string \$errstr) use (&\$warnings): bool {
        \$warnings[] = \$errstr;
        return true;
    });
    \$id = \Elastic\Apm\Impl\Util\IdGenerator::generateId(8);
    \$floats = [];
    for (\$i = 0; \$i < {$floatsCount}; ++\$i) {
        \$floats[] = \Elastic\Apm\Impl\Util\RandomUtil::generate01Float();
    }
    echo json_encode(['id' => \$id, 'floats' => \$floats, 'warnings' => \$warnings, 'randomFunctionsCalls' => \$randomFunctionsCalls]);
}
PHP;

        // -n: do not load php.ini (for example the agent extension) in the child process
        $command = escapeshellarg(PHP_BINARY) . ' -n ' . $phpOptions;
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fwrite($pipes[0], $script);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertSame(0, $exitCode, 'stdout: ' . var_export($stdout, true) . ' stderr: ' . $stderr);
        self::assertSame('', $stderr);
        self::assertIsString($stdout);
        $decoded = json_decode($stdout, true);
        self::assertIsArray($decoded, $stdout);
        /** @var array{id: string, floats: array<float>, warnings: array<string>, randomFunctionsCalls: int} $decoded */
        return $decoded;
    }
}
