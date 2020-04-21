--TEST--
Extensions PHP API to get transaction_id/trace_id returns null when Agent is disabled
--SKIPIF--
<?php
if (!extension_loaded('elasticapm')) {
	echo 'skip';
}
?>
--INI--
elasticapm.enabled=no
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util//bootstrap.php';

elasticApmAssertSame(
    false,
    elasticapm_is_enabled(),
    "elasticapm_is_enabled()"
);

elasticApmAssertSame(
    null,
    elasticapm_get_current_transaction_id(),
    "elasticapm_get_current_transaction_id()"
);

elasticApmAssertSame(
    null,
    elasticapm_get_current_trace_id(),
    "elasticapm_get_current_trace_id()"
);

echo 'Test completed'
?>
--EXPECT--
Test completed
