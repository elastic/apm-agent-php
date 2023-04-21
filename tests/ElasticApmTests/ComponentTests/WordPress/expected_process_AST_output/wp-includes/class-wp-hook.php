<?php

/** @noinspection ALL */

final class WP_Hook extends \ElasticApmTests\ComponentTests\WordPress\WordPressMockWpHook {
    /**
     * @param string   $hook_name     The name of the filter to add the callback to.
     * @param callable $callback      The callback to be run when the filter is applied.
     * @param int      $priority      The order in which the functions associated with a particular filter
     *                                are executed. Lower numbers correspond with earlier execution,
     *                                and functions with the same priority are executed in the order
     *                                in which they were added to the filter.
     * @param int      $accepted_args The number of arguments the function accepts.
     */
    public function add_filter( $hook_name, $callback, $priority, $accepted_args ) {/* <<< BEGIN Elasitc APM tests marker to fold into one line */

        \elastic_apm_ast_instrumentation_pre_hook(__CLASS__, __FUNCTION__, [$hook_name, &$callback]);

        {
        /* >>> END Elasitc APM tests marker to fold into one line */
        $this->mockImplAddFilter($hook_name, $callback, $priority);
    } }

    private function dummy_last_method(int $x): int
    {
        return $x * 2;
    }
}
