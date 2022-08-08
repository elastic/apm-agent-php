<?php

namespace Symfony\Config\Security;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class AccessDecisionManagerConfig 
{
    private $strategy;
    private $service;
    private $strategyService;
    private $allowIfAllAbstain;
    private $allowIfEqualGrantedDenied;
    private $_usedProperties = [];

    /**
     * @default null
     * @param ParamConfigurator|'affirmative'|'consensus'|'unanimous'|'priority' $value
     * @return $this
     */
    public function strategy($value): static
    {
        $this->_usedProperties['strategy'] = true;
        $this->strategy = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function service($value): static
    {
        $this->_usedProperties['service'] = true;
        $this->service = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function strategyService($value): static
    {
        $this->_usedProperties['strategyService'] = true;
        $this->strategyService = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function allowIfAllAbstain($value): static
    {
        $this->_usedProperties['allowIfAllAbstain'] = true;
        $this->allowIfAllAbstain = $value;

        return $this;
    }

    /**
     * @default true
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function allowIfEqualGrantedDenied($value): static
    {
        $this->_usedProperties['allowIfEqualGrantedDenied'] = true;
        $this->allowIfEqualGrantedDenied = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('strategy', $value)) {
            $this->_usedProperties['strategy'] = true;
            $this->strategy = $value['strategy'];
            unset($value['strategy']);
        }

        if (array_key_exists('service', $value)) {
            $this->_usedProperties['service'] = true;
            $this->service = $value['service'];
            unset($value['service']);
        }

        if (array_key_exists('strategy_service', $value)) {
            $this->_usedProperties['strategyService'] = true;
            $this->strategyService = $value['strategy_service'];
            unset($value['strategy_service']);
        }

        if (array_key_exists('allow_if_all_abstain', $value)) {
            $this->_usedProperties['allowIfAllAbstain'] = true;
            $this->allowIfAllAbstain = $value['allow_if_all_abstain'];
            unset($value['allow_if_all_abstain']);
        }

        if (array_key_exists('allow_if_equal_granted_denied', $value)) {
            $this->_usedProperties['allowIfEqualGrantedDenied'] = true;
            $this->allowIfEqualGrantedDenied = $value['allow_if_equal_granted_denied'];
            unset($value['allow_if_equal_granted_denied']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['strategy'])) {
            $output['strategy'] = $this->strategy;
        }
        if (isset($this->_usedProperties['service'])) {
            $output['service'] = $this->service;
        }
        if (isset($this->_usedProperties['strategyService'])) {
            $output['strategy_service'] = $this->strategyService;
        }
        if (isset($this->_usedProperties['allowIfAllAbstain'])) {
            $output['allow_if_all_abstain'] = $this->allowIfAllAbstain;
        }
        if (isset($this->_usedProperties['allowIfEqualGrantedDenied'])) {
            $output['allow_if_equal_granted_denied'] = $this->allowIfEqualGrantedDenied;
        }

        return $output;
    }

}
