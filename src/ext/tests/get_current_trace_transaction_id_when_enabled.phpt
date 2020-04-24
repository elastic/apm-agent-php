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

elasticApmAssertSame("elasticapm_is_enabled()", elasticapm_is_enabled(), true);

elasticApmAssertSame("strlen( elasticapm_get_current_transaction_id() )", strlen( elasticapm_get_current_transaction_id() ), 16);

elasticApmAssertSame("strlen( elasticapm_get_current_trace_id() )", strlen( elasticapm_get_current_trace_id() ), 32);

echo 'Test completed'
?>
--EXPECT--
Test completed
