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

/** @noinspection SpellCheckingInspection */

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\Impl\AutoInstrument\Util\DbAutoInstrumentationUtil;
use Elastic\Apm\Impl\AutoInstrument\Util\DbStatementSanitizer;
use ElasticApmTests\UnitTests\Util\TracerUnitTestCaseBase;

class DbStatementSanitizerTest extends TracerUnitTestCaseBase
{
    private const SECRET_STATEMENT = "SELECT * FROM t WHERE a = 'secret-value' AND b = 424242";

    /**
     * @return iterable<string, array{string, string}>
     */
    public function dataProviderForTestSanitize(): iterable
    {
        $dataSets = [
            'single quote' => ["SELECT * FROM t WHERE a = 'abc'", 'SELECT * FROM t WHERE a = ?'],
            'double quote' => ['SELECT * FROM t WHERE a = "abc"', 'SELECT * FROM t WHERE a = ?'],
            'doubled single quote' => ["SELECT * FROM t WHERE a = 'it''s a secret'", 'SELECT * FROM t WHERE a = ?'],
            'doubled double quote' => ['SELECT * FROM t WHERE a = "say ""hi"""', 'SELECT * FROM t WHERE a = ?'],
            'backslash escape' => ["SELECT * FROM t WHERE a = 'x\\'y' AND b = 'z'", 'SELECT * FROM t WHERE a = ? AND b = ?'],
            'unterminated quote' => ["SELECT * FROM t WHERE a = 'abc", 'SELECT * FROM t WHERE a = ?'],
            'token' => [
                "SELECT `oauth_token`.* FROM `oauth_token` WHERE (`oauth_token`.`token`='eyJhbGciOiJIUzI1NiJ9.eyJ0IjoxfQ.c2ln')",
                'SELECT `oauth_token`.* FROM `oauth_token` WHERE (`oauth_token`.`token`=?)',
            ],
            'email' => ["SELECT * FROM customer_entity WHERE email = 'jane.doe@example.test'", 'SELECT * FROM customer_entity WHERE email = ?'],
            'integer' => ['SELECT * FROM t WHERE a = 42 LIMIT 10 OFFSET 20', 'SELECT * FROM t WHERE a = ? LIMIT ? OFFSET ?'],
            'negative integer' => ['SELECT * FROM t WHERE a = -3', 'SELECT * FROM t WHERE a = -?'],
            'decimal' => ['SELECT * FROM t WHERE a = 1.25', 'SELECT * FROM t WHERE a = ?'],
            'leading dot decimal' => ['SELECT * FROM t WHERE a = .5', 'SELECT * FROM t WHERE a = ?'],
            'exponent' => ['SELECT * FROM t WHERE a = 1e5', 'SELECT * FROM t WHERE a = ?'],
            'hex' => ['SELECT * FROM t WHERE a = 0xDEADBEEF', 'SELECT * FROM t WHERE a = ?'],
            'hex string' => ["SELECT * FROM t WHERE a = x'ABCD'", 'SELECT * FROM t WHERE a = x?'],
            'binary' => ['SELECT * FROM t WHERE a = 0b101', 'SELECT * FROM t WHERE a = ?'],
            'IN list' => ['SELECT * FROM t WHERE entity_id IN (1, 2, 3)', 'SELECT * FROM t WHERE entity_id IN (?, ?, ?)'],
            'block comment' => ['SELECT /* secret note */ * FROM t', 'SELECT ? * FROM t'],
            'line comment' => ["SELECT * FROM t -- secret note\nWHERE a = ?", "SELECT * FROM t ?\nWHERE a = ?"],
            'hash comment' => ["SELECT * FROM t # secret note\nWHERE a = ?", "SELECT * FROM t ?\nWHERE a = ?"],
            'comment markers inside literal' => ["SELECT * FROM t WHERE a = 'x /* y' AND b = 'z */'", 'SELECT * FROM t WHERE a = ? AND b = ?'],
            'line comment marker inside literal' => ["SELECT 'a -- b'\nWHERE c = 'second'", "SELECT ?\nWHERE c = ?"],
            'hash marker inside literal' => ["SELECT 'a # b'\nWHERE c = 'second'", "SELECT ?\nWHERE c = ?"],
            'identifier starting with a digit' => ['SELECT * FROM 3rd_table', 'SELECT * FROM 3rd_table'],
            'numbered placeholders' => ['SELECT * FROM t WHERE a = :1 AND b = @2', 'SELECT * FROM t WHERE a = :1 AND b = @2'],
            'identifiers and placeholders' => [
                'SELECT `t1`.`c2` FROM `catalog_category_entity` AS `e` WHERE e.entity_id = :id AND x = ?',
                'SELECT `t1`.`c2` FROM `catalog_category_entity` AS `e` WHERE e.entity_id = :id AND x = ?',
            ],
            'empty statement' => ['', ''],
        ];

        yield from $dataSets;
    }

    /**
     * @dataProvider dataProviderForTestSanitize
     */
    public function testSanitize(string $statement, string $expected): void
    {
        self::assertSame($expected, DbStatementSanitizer::sanitize($statement));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public function dataProviderForTestLongLiteral(): iterable
    {
        yield 'comment markers' => [str_repeat('/* ', 5000) . str_repeat('-- ', 5000)];
        yield 'token' => [str_repeat('eyJhbGciOiJIUzI1NiJ9.', 1500)];
    }

    /**
     * @dataProvider dataProviderForTestLongLiteral
     */
    public function testLongLiteral(string $literal): void
    {
        self::assertSame('SELECT * FROM t WHERE a = ?', DbStatementSanitizer::sanitize("SELECT * FROM t WHERE a = '" . $literal . "'"));
    }

    /**
     * @param callable(): void $callback
     */
    private static function withForcedPcreFailure(callable $callback): void
    {
        ini_set('pcre.jit', '0');
        ini_set('pcre.backtrack_limit', '1');
        try {
            $callback();
        } finally {
            ini_restore('pcre.jit');
            ini_restore('pcre.backtrack_limit');
        }
    }

    public function testSanitizeReturnsNullOnPcreFailure(): void
    {
        self::withForcedPcreFailure(
            function (): void {
                self::assertNull(DbStatementSanitizer::sanitize(self::SECRET_STATEMENT));
            }
        );
    }

    public function testBeginDbSpanUsesSanitizedStatement(): void
    {
        $tx = $this->tracer->beginCurrentTransaction('test_TX_name', 'test_TX_type');
        DbAutoInstrumentationUtil::beginDbSpan('PDO', 'query', 'test_DB_type', 'test_DB_name', self::SECRET_STATEMENT)->end();
        $tx->end();

        $span = $this->mockEventSink->singleSpan();
        self::assertSame('SELECT * FROM t WHERE a = ? AND b = ?', $span->name);
        self::assertNotNull($span->context);
        self::assertNotNull($span->context->db);
        self::assertSame('SELECT * FROM t WHERE a = ? AND b = ?', $span->context->db->statement);
    }

    public function testBeginDbSpanFallsBackToCallNameOnPcreFailure(): void
    {
        $tx = $this->tracer->beginCurrentTransaction('test_TX_name', 'test_TX_type');
        self::withForcedPcreFailure(
            function (): void {
                DbAutoInstrumentationUtil::beginDbSpan('PDO', 'query', 'test_DB_type', 'test_DB_name', self::SECRET_STATEMENT)->end();
            }
        );
        $tx->end();

        $span = $this->mockEventSink->singleSpan();
        self::assertSame('PDO::query', $span->name);
        self::assertTrue($span->context === null || $span->context->db === null || $span->context->db->statement === null);
    }

    public function testBeginDbSpanWithoutStatementUsesCallName(): void
    {
        $tx = $this->tracer->beginCurrentTransaction('test_TX_name', 'test_TX_type');
        DbAutoInstrumentationUtil::beginDbSpan('PDO', 'commit', 'test_DB_type', 'test_DB_name', null)->end();
        $tx->end();

        self::assertSame('PDO::commit', $this->mockEventSink->singleSpan()->name);
    }
}
