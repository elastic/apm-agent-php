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
#[MyDummyAttribute('get_template attribute arg')]
function get_template(
    /** doc comment for param1 */
    #[MyDummyAttribute('param1 attribute arg')]
    int $param1 = __LINE__,
    ?string $param2 = MyDummyAttribute::DUMMY_CONST,
    /** doc comment for param3 */
    #[MyDummyAttribute()]
    float &$param3 = MY_DUMMY_GLOBAL_FLOAT_CONST
): ?string {
    /**
     * get_template() body
     */
    return WordPressMockBridge::$activeTheme;
}

function &dummy_last_function_in_theme_php(int &$x): int {
    return $x;
}
