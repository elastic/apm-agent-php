<?php

namespace Symfony\Config\Security\ProviderConfig;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class LdapConfig 
{
    private $service;
    private $baseDn;
    private $searchDn;
    private $searchPassword;
    private $extraFields;
    private $defaultRoles;
    private $uidKey;
    private $filter;
    private $passwordAttribute;
    private $_usedProperties = [];

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
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function baseDn($value): static
    {
        $this->_usedProperties['baseDn'] = true;
        $this->baseDn = $value;

        return $this;
    }

    /**
     * @default null
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
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function searchPassword($value): static
    {
        $this->_usedProperties['searchPassword'] = true;
        $this->searchPassword = $value;

        return $this;
    }

    /**
     * @param ParamConfigurator|list<ParamConfigurator|mixed> $value
     *
     * @return $this
     */
    public function extraFields(ParamConfigurator|array $value): static
    {
        $this->_usedProperties['extraFields'] = true;
        $this->extraFields = $value;

        return $this;
    }

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function defaultRoles(mixed $value): static
    {
        $this->_usedProperties['defaultRoles'] = true;
        $this->defaultRoles = $value;

        return $this;
    }

    /**
     * @default 'sAMAccountName'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function uidKey($value): static
    {
        $this->_usedProperties['uidKey'] = true;
        $this->uidKey = $value;

        return $this;
    }

    /**
     * @default '({uid_key}={username})'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function filter($value): static
    {
        $this->_usedProperties['filter'] = true;
        $this->filter = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function passwordAttribute($value): static
    {
        $this->_usedProperties['passwordAttribute'] = true;
        $this->passwordAttribute = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('service', $value)) {
            $this->_usedProperties['service'] = true;
            $this->service = $value['service'];
            unset($value['service']);
        }

        if (array_key_exists('base_dn', $value)) {
            $this->_usedProperties['baseDn'] = true;
            $this->baseDn = $value['base_dn'];
            unset($value['base_dn']);
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

        if (array_key_exists('extra_fields', $value)) {
            $this->_usedProperties['extraFields'] = true;
            $this->extraFields = $value['extra_fields'];
            unset($value['extra_fields']);
        }

        if (array_key_exists('default_roles', $value)) {
            $this->_usedProperties['defaultRoles'] = true;
            $this->defaultRoles = $value['default_roles'];
            unset($value['default_roles']);
        }

        if (array_key_exists('uid_key', $value)) {
            $this->_usedProperties['uidKey'] = true;
            $this->uidKey = $value['uid_key'];
            unset($value['uid_key']);
        }

        if (array_key_exists('filter', $value)) {
            $this->_usedProperties['filter'] = true;
            $this->filter = $value['filter'];
            unset($value['filter']);
        }

        if (array_key_exists('password_attribute', $value)) {
            $this->_usedProperties['passwordAttribute'] = true;
            $this->passwordAttribute = $value['password_attribute'];
            unset($value['password_attribute']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['service'])) {
            $output['service'] = $this->service;
        }
        if (isset($this->_usedProperties['baseDn'])) {
            $output['base_dn'] = $this->baseDn;
        }
        if (isset($this->_usedProperties['searchDn'])) {
            $output['search_dn'] = $this->searchDn;
        }
        if (isset($this->_usedProperties['searchPassword'])) {
            $output['search_password'] = $this->searchPassword;
        }
        if (isset($this->_usedProperties['extraFields'])) {
            $output['extra_fields'] = $this->extraFields;
        }
        if (isset($this->_usedProperties['defaultRoles'])) {
            $output['default_roles'] = $this->defaultRoles;
        }
        if (isset($this->_usedProperties['uidKey'])) {
            $output['uid_key'] = $this->uidKey;
        }
        if (isset($this->_usedProperties['filter'])) {
            $output['filter'] = $this->filter;
        }
        if (isset($this->_usedProperties['passwordAttribute'])) {
            $output['password_attribute'] = $this->passwordAttribute;
        }

        return $output;
    }

}
