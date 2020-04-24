--TEST--
Extensions PHP API to get transaction_id/trace_id returns null when Agent is disabled
--SKIPIF--
<?php if ( ! extension_loaded( 'elasticapm' ) ) die( 'skip'.'Extension elasticapm must be installed' ); ?>
--INI--
elasticapm.enabled=no
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/../tests_util/tests_util.php';

elasticApmAssertSame("elasticapm_is_enabled()", elasticapm_is_enabled(), false);

elasticApmAssertSame("elasticapm_get_current_transaction_id()", elasticapm_get_current_transaction_id(), null);

elasticApmAssertSame("elasticapm_get_current_trace_id()", elasticapm_get_current_trace_id(), null);

echo 'Test completed'
?>
--EXPECT--
Test completed
