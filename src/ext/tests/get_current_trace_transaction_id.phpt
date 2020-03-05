--TEST--
Check if elasticapm provides transaction_id and trace_id
--SKIPIF--
<?php
if (!extension_loaded('elasticapm')) {
	echo 'skip';
}
?>
--INI--
elasticapm.autoload_file=/mnt/hgfs/Elastic_Dev/PHP_Agent/Linux/_TEMP/work/PHP_my_fork_2/src/autoload.php
--FILE--
<?php
if ( elasticapm_is_enabled() ) {
    if ( strlen( elasticapm_get_current_transaction_id() ) !== 16 ) {
    	echo 'strlen( elasticapm_get_current_transaction_id() ): ' . strlen( elasticapm_get_current_transaction_id() );
    }
    if ( strlen( elasticapm_get_current_trace_id() ) !== 32 ) {
    	echo 'strlen( elasticapm_get_current_trace_id() ): ' . strlen( elasticapm_get_current_trace_id() );
    }
} else {
    if ( elasticapm_get_current_transaction_id() !== null ) {
    	echo 'elasticapm_get_current_transaction_id(): ' . elasticapm_get_current_transaction_id();
    }
    if ( elasticapm_get_current_trace_id() !== null ) {
    	echo 'elasticapm_get_current_trace_id(): ' . elasticapm_get_current_trace_id();
    }
}

echo 'Test completed'
?>
--EXPECT--
Test completed
