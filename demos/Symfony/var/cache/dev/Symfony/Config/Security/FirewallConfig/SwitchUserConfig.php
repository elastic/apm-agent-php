<?php

namespace Symfony\Config\Security\FirewallConfig;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class SwitchUserConfig 
{
    private $provider;
    private $parameter;
    private $role;
    private $_usedProperties = [];

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function provider($value): static
    {
        $this->_usedProperties['provider'] = true;
        $this->provider = $value;

        return $this;
    }

    /**
     * @default '_switch_user'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function parameter($value): static
    {
        $this->_usedProperties['parameter'] = true;
        $this->parameter = $value;

        return $this;
    }

    /**
     * @default 'ROLE_ALLOWED_TO_SWITCH'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function role($value): static
    {
        $this->_usedProperties['role'] = true;
        $this->role = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('provider', $value)) {
            $this->_usedProperties['provider'] = true;
            $this->provider = $value['provider'];
            unset($value['provider']);
        }

        if (array_key_exists('parameter', $value)) {
            $this->_usedProperties['parameter'] = true;
            $this->parameter = $value['parameter'];
            unset($value['parameter']);
        }

        if (array_key_exists('role', $value)) {
            $this->_usedProperties['role'] = true;
            $this->role = $value['role'];
            unset($value['role']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['provider'])) {
            $output['provider'] = $this->provider;
        }
        if (isset($this->_usedProperties['parameter'])) {
            $output['parameter'] = $this->parameter;
        }
        if (isset($this->_usedProperties['role'])) {
            $output['role'] = $this->role;
        }

        return $output;
    }

}
