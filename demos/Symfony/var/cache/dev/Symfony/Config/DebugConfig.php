<?php

namespace Symfony\Config;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class DebugConfig implements \Symfony\Component\Config\Builder\ConfigBuilderInterface
{
    private $maxItems;
    private $minDepth;
    private $maxStringLength;
    private $dumpDestination;
    private $theme;
    private $_usedProperties = [];

    /**
     * Max number of displayed items past the first level, -1 means no limit
     * @default 2500
     * @param ParamConfigurator|int $value
     * @return $this
     */
    public function maxItems($value): static
    {
        $this->_usedProperties['maxItems'] = true;
        $this->maxItems = $value;

        return $this;
    }

    /**
     * Minimum tree depth to clone all the items, 1 is default
     * @default 1
     * @param ParamConfigurator|int $value
     * @return $this
     */
    public function minDepth($value): static
    {
        $this->_usedProperties['minDepth'] = true;
        $this->minDepth = $value;

        return $this;
    }

    /**
     * Max length of displayed strings, -1 means no limit
     * @default -1
     * @param ParamConfigurator|int $value
     * @return $this
     */
    public function maxStringLength($value): static
    {
        $this->_usedProperties['maxStringLength'] = true;
        $this->maxStringLength = $value;

        return $this;
    }

    /**
     * A stream URL where dumps should be written to
     * @example php://stderr, or tcp://%env(VAR_DUMPER_SERVER)% when using the "server:dump" command
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function dumpDestination($value): static
    {
        $this->_usedProperties['dumpDestination'] = true;
        $this->dumpDestination = $value;

        return $this;
    }

    /**
     * Changes the color of the dump() output when rendered directly on the templating. "dark" (default) or "light"
     * @example dark
     * @default 'dark'
     * @param ParamConfigurator|'dark'|'light' $value
     * @return $this
     */
    public function theme($value): static
    {
        $this->_usedProperties['theme'] = true;
        $this->theme = $value;

        return $this;
    }

    public function getExtensionAlias(): string
    {
        return 'debug';
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('max_items', $value)) {
            $this->_usedProperties['maxItems'] = true;
            $this->maxItems = $value['max_items'];
            unset($value['max_items']);
        }

        if (array_key_exists('min_depth', $value)) {
            $this->_usedProperties['minDepth'] = true;
            $this->minDepth = $value['min_depth'];
            unset($value['min_depth']);
        }

        if (array_key_exists('max_string_length', $value)) {
            $this->_usedProperties['maxStringLength'] = true;
            $this->maxStringLength = $value['max_string_length'];
            unset($value['max_string_length']);
        }

        if (array_key_exists('dump_destination', $value)) {
            $this->_usedProperties['dumpDestination'] = true;
            $this->dumpDestination = $value['dump_destination'];
            unset($value['dump_destination']);
        }

        if (array_key_exists('theme', $value)) {
            $this->_usedProperties['theme'] = true;
            $this->theme = $value['theme'];
            unset($value['theme']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['maxItems'])) {
            $output['max_items'] = $this->maxItems;
        }
        if (isset($this->_usedProperties['minDepth'])) {
            $output['min_depth'] = $this->minDepth;
        }
        if (isset($this->_usedProperties['maxStringLength'])) {
            $output['max_string_length'] = $this->maxStringLength;
        }
        if (isset($this->_usedProperties['dumpDestination'])) {
            $output['dump_destination'] = $this->dumpDestination;
        }
        if (isset($this->_usedProperties['theme'])) {
            $output['theme'] = $this->theme;
        }

        return $output;
    }

}
