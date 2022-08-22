<?php

namespace Symfony\Config\Security\FirewallConfig;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class HttpBasicLdapConfig 
{
    private $provider;
    private $realm;
    private $service;
    private $dnString;
    private $queryString;
    private $searchDn;
    private $searchPassword;
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
     * @default 'Secured Area'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function realm($value): static
    {
        $this->_usedProperties['realm'] = true;
        $this->realm = $value;

        return $this;
    }

    /**
     * @default 'ldap'
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
     * @default '{username}'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function dnString($value): static
    {
        $this->_usedProperties['dnString'] = true;
        $this->dnString = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function queryString($value): static
    {
        $this->_usedProperties['queryString'] = true;
        $this->queryString = $value;

        return $this;
    }

    /**
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function searchDn($value): static
    {
        $this->_usedProperties['searchDn'] = true;
        $this->searchDn = $value;

        return $this;
    }

    /**
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function searchPassword($value): static
    {
        $this->_usedProperties['searchPassword'] = true;
        $this->searchPassword = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('provider', $value)) {
            $this->_usedProperties['provider'] = true;
            $this->provider = $value['provider'];
            unset($value['provider']);
        }

        if (array_key_exists('realm', $value)) {
            $this->_usedProperties['realm'] = true;
            $this->realm = $value['realm'];
            unset($value['realm']);
        }

        if (array_key_exists('service', $value)) {
            $this->_usedProperties['service'] = true;
            $this->service = $value['service'];
            unset($value['service']);
        }

        if (array_key_exists('dn_string', $value)) {
            $this->_usedProperties['dnString'] = true;
            $this->dnString = $value['dn_string'];
            unset($value['dn_string']);
        }

        if (array_key_exists('query_string', $value)) {
            $this->_usedProperties['queryString'] = true;
            $this->queryString = $value['query_string'];
            unset($value['query_string']);
        }

        if (array_key_exists('search_dn', $value)) {
            $this->_usedProperties['searchDn'] = true;
            $this->searchDn = $value['search_dn'];
            unset($value['search_dn']);
        }

        if (array_key_exists('search_password', $value)) {
            $this->_usedProperties['searchPassword'] = true;
            $this->searchPassword = $value['search_password'];
            unset($value['search_password']);
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
        if (isset($this->_usedProperties['realm'])) {
            $output['realm'] = $this->realm;
        }
        if (isset($this->_usedProperties['service'])) {
            $output['service'] = $this->service;
        }
        if (isset($this->_usedProperties['dnString'])) {
            $output['dn_string'] = $this->dnString;
        }
        if (isset($this->_usedProperties['queryString'])) {
            $output['query_string'] = $this->queryString;
        }
        if (isset($this->_usedProperties['searchDn'])) {
            $output['search_dn'] = $this->searchDn;
        }
        if (isset($this->_usedProperties['searchPassword'])) {
            $output['search_password'] = $this->searchPassword;
        }

        return $output;
    }

}
