<?php

/** @noinspection ALL */

use ElasticApmTests\ComponentTests\WordPress\WordPressMockBridge;

#[Attribute]
class MyDummyAttribute
{
    public const DUMMY_CONST = null;

    /** @var ?string */
    private $value;

    public function __construct(?string $value = null)
    {
        $this->value = $value;
    }
}

define('MY_DUMMY_GLOBAL_FLOAT_CONST', 3.1416);

/**
 * Retrieves name of the active theme.
 *
 * @since 1.5.0
 *
 * @return string Template name.
 */
{ #[MyDummyAttribute('get_template attribute arg')]
function get_templateElasticApmWrapped_suffixToBeRemovedByElasticApmTests(
    /** doc comment for param1 */
    #[MyDummyAttribute('param1 attribute arg')]
    int $param1 = __LINE__,
    ?string $param2 = MyDummyAttribute::DUMMY_CONST,
    /** doc comment for param3 */
    #[MyDummyAttribute()]
    float &$param3 = MY_DUMMY_GLOBAL_FLOAT_CONST
) {
    /**
     * get_template() body
     */
    return WordPressMockBridge::$activeTheme;
}/* <<< BEGIN Elasitc APM tests marker to fold into one line */

/**
 * Retrieves name of the active theme.
 *
 * @since 1.5.0
 *
 * @return string Template name.
 */
#[MyDummyAttribute('get_template attribute arg')]
function get_template(
    /** doc comment for param1 */
    #[MyDummyAttribute('param1 attribute arg')]
    int $param1 = __LINE__,
    ?string $param2 = MyDummyAttribute::DUMMY_CONST,
    /** doc comment for param3 */
    #[MyDummyAttribute()]
    float &$param3 = MY_DUMMY_GLOBAL_FLOAT_CONST
) {
    $args = \func_get_args();
    $postHook = \elastic_apm_ast_instrumentation_pre_hook(/* instrumentedClassFullName */ null, __FUNCTION__, $args);
    try {
        $retVal = get_templateElasticApmWrapped_suffixToBeRemovedByElasticApmTests(...$args);
        if ($postHook !== null) $postHook(/* thrown */ null, $retVal);
        return $retVal;
    } catch (\Throwable $thrown) {
        if ($postHook !== null) $postHook($thrown, /* retVal */ null);
        throw $thrown;
    }
}
}
/* >>> END Elasitc APM tests marker to fold into one line */

function &dummy_last_function_in_theme_php(int &$x): int {
    return $x;
}
