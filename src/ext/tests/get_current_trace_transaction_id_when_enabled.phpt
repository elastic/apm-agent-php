--TEST--
Extensions PHP API to get transaction_id/trace_id returns valid values when Agent is enabled
--SKIPIF--
<?php
if (!extension_loaded('elasticapm')) {
	echo 'skip';
}
?>
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

elasticApmAssertSame(
    true,
    elasticapm_is_enabled(),
    "elasticapm_is_enabled()"
);

elasticApmAssertSame(
    16,
    strlen( elasticapm_get_current_transaction_id() ),
    "strlen( elasticapm_get_current_transaction_id() )"
);

elasticApmAssertSame(
    32,
    strlen( elasticapm_get_current_trace_id() ),
    "strlen( elasticapm_get_current_trace_id() )"
);

echo 'Test completed'
?>
--EXPECT--
Test completed
