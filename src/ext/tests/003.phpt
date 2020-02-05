--TEST--
Check if elasticapm provides transaction_id and trace_id
--SKIPIF--
<?php
if (!extension_loaded('elasticapm')) {
	echo 'skip';
}
?>
--FILE--
<?php
if ( elasticApmIsEnabled() ) {
    if ( strlen( elasticApmGetCurrentTransactionId() ) !== 16 ) {
    	echo 'strlen( elasticApmGetCurrentTransactionId() ): ' . strlen( elasticApmGetCurrentTransactionId() );
    }
    if ( strlen( elasticApmGetCurrentTraceId() ) !== 32 ) {
    	echo 'strlen( elasticApmGetCurrentTraceId() ): ' . strlen( elasticApmGetCurrentTraceId() );
    }
} else {
    if ( elasticApmGetCurrentTransactionId() !== null ) {
    	echo 'elasticApmGetCurrentTransactionId(): ' . elasticApmGetCurrentTransactionId();
    }
    if ( elasticApmGetCurrentTraceId() !== null ) {
    	echo 'elasticApmGetCurrentTraceId(): ' . elasticApmGetCurrentTraceId();
    }
}
?>
--EXPECT--
