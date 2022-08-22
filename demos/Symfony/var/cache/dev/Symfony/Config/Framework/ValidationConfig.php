<?php

namespace Symfony\Config\Framework;

require_once __DIR__.\DIRECTORY_SEPARATOR.'Validation'.\DIRECTORY_SEPARATOR.'MappingConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Validation'.\DIRECTORY_SEPARATOR.'NotCompromisedPasswordConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Validation'.\DIRECTORY_SEPARATOR.'AutoMappingConfig.php';

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class ValidationConfig 
{
    private $enabled;
    private $cache;
    private $enableAnnotations;
    private $staticMethod;
    private $translationDomain;
    private $emailValidationMode;
    private $mapping;
    private $notCompromisedPassword;
    private $autoMapping;
    private $_usedProperties = [];

    /**
     * @default true
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
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function cache($value): static
    {
        $this->_usedProperties['cache'] = true;
        $this->cache = $value;

        return $this;
    }

    /**
     * @default true
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function enableAnnotations($value): static
    {
        $this->_usedProperties['enableAnnotations'] = true;
        $this->enableAnnotations = $value;

        return $this;
    }

    /**
     * @param ParamConfigurator|list<ParamConfigurator|mixed> $value
     *
     * @return $this
     */
    public function staticMethod(ParamConfigurator|array $value): static
    {
        $this->_usedProperties['staticMethod'] = true;
        $this->staticMethod = $value;

        return $this;
    }

    /**
     * @default 'validators'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function translationDomain($value): static
    {
        $this->_usedProperties['translationDomain'] = true;
        $this->translationDomain = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|'html5'|'loose'|'strict' $value
     * @return $this
     */
    public function emailValidationMode($value): static
    {
        $this->_usedProperties['emailValidationMode'] = true;
        $this->emailValidationMode = $value;

        return $this;
    }

    /**
     * @default {"paths":[]}
    */
    public function mapping(array $value = []): \Symfony\Config\Framework\Validation\MappingConfig
    {
        if (null === $this->mapping) {
            $this->_usedProperties['mapping'] = true;
            $this->mapping = new \Symfony\Config\Framework\Validation\MappingConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "mapping()" has already been initialized. You cannot pass values the second time you call mapping().');
        }

        return $this->mapping;
    }

    /**
     * @default {"enabled":true,"endpoint":null}
    */
    public function notCompromisedPassword(array $value = []): \Symfony\Config\Framework\Validation\NotCompromisedPasswordConfig
    {
        if (null === $this->notCompromisedPassword) {
            $this->_usedProperties['notCompromisedPassword'] = true;
            $this->notCompromisedPassword = new \Symfony\Config\Framework\Validation\NotCompromisedPasswordConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "notCompromisedPassword()" has already been initialized. You cannot pass values the second time you call notCompromisedPassword().');
        }

        return $this->notCompromisedPassword;
    }

    /**
     * A collection of namespaces for which auto-mapping will be enabled by default, or null to opt-in with the EnableAutoMapping constraint.
     * @example []
     * @example ["validator.property_info_loader"]
     * @return \Symfony\Config\Framework\Validation\AutoMappingConfig|$this
     */
    public function autoMapping(string $namespace, mixed $value = []): \Symfony\Config\Framework\Validation\AutoMappingConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['autoMapping'] = true;
            $this->autoMapping[$namespace] = $value;

            return $this;
        }

        if (!isset($this->autoMapping[$namespace]) || !$this->autoMapping[$namespace] instanceof \Symfony\Config\Framework\Validation\AutoMappingConfig) {
            $this->_usedProperties['autoMapping'] = true;
            $this->autoMapping[$namespace] = new \Symfony\Config\Framework\Validation\AutoMappingConfig($value);
        } elseif (1 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "autoMapping()" has already been initialized. You cannot pass values the second time you call autoMapping().');
        }

        return $this->autoMapping[$namespace];
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('enabled', $value)) {
            $this->_usedProperties['enabled'] = true;
            $this->enabled = $value['enabled'];
            unset($value['enabled']);
        }

        if (array_key_exists('cache', $value)) {
            $this->_usedProperties['cache'] = true;
            $this->cache = $value['cache'];
            unset($value['cache']);
        }

        if (array_key_exists('enable_annotations', $value)) {
            $this->_usedProperties['enableAnnotations'] = true;
            $this->enableAnnotations = $value['enable_annotations'];
            unset($value['enable_annotations']);
        }

        if (array_key_exists('static_method', $value)) {
            $this->_usedProperties['staticMethod'] = true;
            $this->staticMethod = $value['static_method'];
            unset($value['static_method']);
        }

        if (array_key_exists('translation_domain', $value)) {
            $this->_usedProperties['translationDomain'] = true;
            $this->translationDomain = $value['translation_domain'];
            unset($value['translation_domain']);
        }

        if (array_key_exists('email_validation_mode', $value)) {
            $this->_usedProperties['emailValidationMode'] = true;
            $this->emailValidationMode = $value['email_validation_mode'];
            unset($value['email_validation_mode']);
        }

        if (array_key_exists('mapping', $value)) {
            $this->_usedProperties['mapping'] = true;
            $this->mapping = new \Symfony\Config\Framework\Validation\MappingConfig($value['mapping']);
            unset($value['mapping']);
        }

        if (array_key_exists('not_compromised_password', $value)) {
            $this->_usedProperties['notCompromisedPassword'] = true;
            $this->notCompromisedPassword = new \Symfony\Config\Framework\Validation\NotCompromisedPasswordConfig($value['not_compromised_password']);
            unset($value['not_compromised_password']);
        }

        if (array_key_exists('auto_mapping', $value)) {
            $this->_usedProperties['autoMapping'] = true;
            $this->autoMapping = array_map(function ($v) { return \is_array($v) ? new \Symfony\Config\Framework\Validation\AutoMappingConfig($v) : $v; }, $value['auto_mapping']);
            unset($value['auto_mapping']);
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
        if (isset($this->_usedProperties['cache'])) {
            $output['cache'] = $this->cache;
        }
        if (isset($this->_usedProperties['enableAnnotations'])) {
            $output['enable_annotations'] = $this->enableAnnotations;
        }
        if (isset($this->_usedProperties['staticMethod'])) {
            $output['static_method'] = $this->staticMethod;
        }
        if (isset($this->_usedProperties['translationDomain'])) {
            $output['translation_domain'] = $this->translationDomain;
        }
        if (isset($this->_usedProperties['emailValidationMode'])) {
            $output['email_validation_mode'] = $this->emailValidationMode;
        }
        if (isset($this->_usedProperties['mapping'])) {
            $output['mapping'] = $this->mapping->toArray();
        }
        if (isset($this->_usedProperties['notCompromisedPassword'])) {
            $output['not_compromised_password'] = $this->notCompromisedPassword->toArray();
        }
        if (isset($this->_usedProperties['autoMapping'])) {
            $output['auto_mapping'] = array_map(function ($v) { return $v instanceof \Symfony\Config\Framework\Validation\AutoMappingConfig ? $v->toArray() : $v; }, $this->autoMapping);
        }

        return $output;
    }

}
