<?php

/** @noinspection ALL */

// Initialize the filter globals.
require __DIR__ . '/class-wp-hook.php';

/** @var WP_Hook[] $wp_filter */
global $wp_filter;
$wp_filter = [];

/**
 * @global WP_Hook[] $wp_filter A multidimensional array of all hooks and the callbacks hooked to them.
 *
 * @param string   $hook_name     The name of the filter to add the callback to.
 * @param callable $callback      The callback to be run when the filter is applied.
 * @param int      $priority      Optional. Used to specify the order in which the functions
 *                                associated with a particular filter are executed.
 *                                Lower numbers correspond with earlier execution,
 *                                and functions with the same priority are executed
 *                                in the order in which they were added to the filter. Default 10.
 * @param int      $accepted_args Optional. The number of arguments the function accepts. Default 1.
 * @return true Always returns true.
 */
function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
    global $wp_filter;

    \ElasticApmTests\ComponentTests\WordPress\WordPressMockBridge::mockImplAddFiler(/* ref */ $wp_filter, $hook_name, $callback, $priority, $accepted_args);

    return true;
}

/**
 * @since 1.2.0
 *
 * @param string                $hook_name The filter hook to which the function to be removed is hooked.
 * @param callable|string|array $callback  The callback to be removed from running when the filter is applied.
 *                                         This function can be called unconditionally to speculatively remove
 *                                         a callback that may or may not exist.
 * @param int                   $priority  Optional. The exact priority used when adding the original
 *                                         filter callback. Default 10.
 * @return bool Whether the function existed before it was removed.
 */
function remove_filter( $hook_name, $callback, $priority = 10 ) {
    global $wp_filter;

    return \ElasticApmTests\ComponentTests\WordPress\WordPressMockBridge::mockImplRemoveFilter(/* ref */ $wp_filter, $hook_name, $callback, $priority);
}


/**
 * @since 6.0.0 Formalized the existing and already documented `...$args` parameter
 *              by adding it to the function signature.
 *
 * @param string $hook_name The name of the filter hook.
 * @param mixed  $value     The value to filter.
 * @param mixed  ...$args   Additional parameters to pass to the callback functions.
 * @return mixed The filtered value after all hooked functions are applied to it.
 */
function apply_filters( $hook_name, $value, ...$args ) {
    global $wp_filter;

    return \ElasticApmTests\ComponentTests\WordPress\WordPressMockBridge::mockImplApplyFilters(/* ref */ $wp_filter, $hook_name, $value, ...$args);
}

/**
 * @since 1.2.0
 * @since 5.3.0 Formalized the existing and already documented `...$arg` parameter
 *              by adding it to the function signature.
 *
 * @param string $hook_name The name of the action to be executed.
 * @param mixed  ...$arg    Optional. Additional arguments which are passed on to the
 *                          functions hooked to the action. Default empty.
 */
function do_action( $hook_name, ...$arg ) {
    global $wp_filter;

    \ElasticApmTests\ComponentTests\WordPress\WordPressMockBridge::mockImplDoAction(/* ref */ $wp_filter, $hook_name, ...$arg);
}

/**
 * @param string                $hook_name Unused. The name of the filter to build ID for.
 * @param callable|string|array $callback  The callback to generate ID for. The callback may
 *                                         or may not exist.
 * @param int                   $priority  Unused. The order in which the functions
 *                                         associated with a particular action are executed.
 * @return string Unique function ID for usage as array key.
 */
{ function _wp_filter_build_unique_id($hook_name, $callback, $priority ) {/* <<< BEGIN Elasitc APM tests marker to fold into one line */

    \elastic_apm_ast_instrumentation_pre_hook(/* instrumentedClassFullName */ null, __FUNCTION__, [$hook_name, &$callback]);

    {
    /* >>> END Elasitc APM tests marker to fold into one line */
	if ( is_string( $callback ) ) {
		return $callback;
	}

	if ( is_object( $callback ) ) {
		// Closures are currently implemented as objects.
		$callback = array( $callback, '' );
	} else {
		$callback = (array) $callback;
	}

	if ( is_object( $callback[0] ) ) {
		// Object class calling.
		return spl_object_hash( $callback[0] ) . $callback[1];
	} elseif ( is_string( $callback[0] ) ) {
		// Static calling.
		return $callback[0] . '::' . $callback[1];
	}
} }/* <<< BEGIN Elasitc APM tests marker to fold into one line */

    \elastic_apm_ast_instrumentation_direct_call(\ELASTIC_APM_WORDPRESS_DIRECT_CALL_METHOD_SET_READY_TO_WRAP_FILTER_CALLBACKS);
}
/* >>> END Elasitc APM tests marker to fold into one line */

function dummy_last_function_in_plugin_php(int $x): int
{
    return $x * 2;
}
