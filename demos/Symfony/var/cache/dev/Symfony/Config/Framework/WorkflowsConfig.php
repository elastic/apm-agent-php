<?php

namespace Symfony\Config\Framework;

require_once __DIR__.\DIRECTORY_SEPARATOR.'Workflows'.\DIRECTORY_SEPARATOR.'WorkflowsConfig.php';

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class WorkflowsConfig 
{
    private $enabled;
    private $workflows;
    private $_usedProperties = [];

    /**
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function enabled($value): static
    {
        $this->_usedProperties['enabled'] = true;
        $this->enabled = $value;

        return $this;
    }

    /**
     * @return \Symfony\Config\Framework\Workflows\WorkflowsConfig|$this
     */
    public function workflows(string $name, mixed $value = []): \Symfony\Config\Framework\Workflows\WorkflowsConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['workflows'] = true;
            $this->workflows[$name] = $value;

            return $this;
        }

        if (!isset($this->workflows[$name]) || !$this->workflows[$name] instanceof \Symfony\Config\Framework\Workflows\WorkflowsConfig) {
            $this->_usedProperties['workflows'] = true;
            $this->workflows[$name] = new \Symfony\Config\Framework\Workflows\WorkflowsConfig($value);
        } elseif (1 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "workflows()" has already been initialized. You cannot pass values the second time you call workflows().');
        }

        return $this->workflows[$name];
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('enabled', $value)) {
            $this->_usedProperties['enabled'] = true;
            $this->enabled = $value['enabled'];
            unset($value['enabled']);
        }

        if (array_key_exists('workflows', $value)) {
            $this->_usedProperties['workflows'] = true;
            $this->workflows = array_map(function ($v) { return \is_array($v) ? new \Symfony\Config\Framework\Workflows\WorkflowsConfig($v) : $v; }, $value['workflows']);
            unset($value['workflows']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['enabled'])) {
            $output['enabled'] = $this->enabled;
        }
        if (isset($this->_usedProperties['workflows'])) {
            $output['workflows'] = array_map(function ($v) { return $v instanceof \Symfony\Config\Framework\Workflows\WorkflowsConfig ? $v->toArray() : $v; }, $this->workflows);
        }

        return $output;
    }

}
