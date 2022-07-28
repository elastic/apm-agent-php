<?php

namespace Symfony\Config\Framework;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class SessionConfig 
{
    private $enabled;
    private $storageFactoryId;
    private $handlerId;
    private $name;
    private $cookieLifetime;
    private $cookiePath;
    private $cookieDomain;
    private $cookieSecure;
    private $cookieHttponly;
    private $cookieSamesite;
    private $useCookies;
    private $gcDivisor;
    private $gcProbability;
    private $gcMaxlifetime;
    private $savePath;
    private $metadataUpdateThreshold;
    private $sidLength;
    private $sidBitsPerCharacter;
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
     * @default 'session.storage.factory.native'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function storageFactoryId($value): static
    {
        $this->_usedProperties['storageFactoryId'] = true;
        $this->storageFactoryId = $value;

        return $this;
    }

    /**
     * @default 'session.handler.native_file'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function handlerId($value): static
    {
        $this->_usedProperties['handlerId'] = true;
        $this->handlerId = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function name($value): static
    {
        $this->_usedProperties['name'] = true;
        $this->name = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function cookieLifetime($value): static
    {
        $this->_usedProperties['cookieLifetime'] = true;
        $this->cookieLifetime = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function cookiePath($value): static
    {
        $this->_usedProperties['cookiePath'] = true;
        $this->cookiePath = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function cookieDomain($value): static
    {
        $this->_usedProperties['cookieDomain'] = true;
        $this->cookieDomain = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|true|false|'auto' $value
     * @return $this
     */
    public function cookieSecure($value): static
    {
        $this->_usedProperties['cookieSecure'] = true;
        $this->cookieSecure = $value;

        return $this;
    }

    /**
     * @default true
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function cookieHttponly($value): static
    {
        $this->_usedProperties['cookieHttponly'] = true;
        $this->cookieHttponly = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|NULL|'lax'|'strict'|'none' $value
     * @return $this
     */
    public function cookieSamesite($value): static
    {
        $this->_usedProperties['cookieSamesite'] = true;
        $this->cookieSamesite = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function useCookies($value): static
    {
        $this->_usedProperties['useCookies'] = true;
        $this->useCookies = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function gcDivisor($value): static
    {
        $this->_usedProperties['gcDivisor'] = true;
        $this->gcDivisor = $value;

        return $this;
    }

    /**
     * @default 1
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function gcProbability($value): static
    {
        $this->_usedProperties['gcProbability'] = true;
        $this->gcProbability = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function gcMaxlifetime($value): static
    {
        $this->_usedProperties['gcMaxlifetime'] = true;
        $this->gcMaxlifetime = $value;

        return $this;
    }

    /**
     * @default '%kernel.cache_dir%/sessions'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function savePath($value): static
    {
        $this->_usedProperties['savePath'] = true;
        $this->savePath = $value;

        return $this;
    }

    /**
     * seconds to wait between 2 session metadata updates
     * @default 0
     * @param ParamConfigurator|int $value
     * @return $this
     */
    public function metadataUpdateThreshold($value): static
    {
        $this->_usedProperties['metadataUpdateThreshold'] = true;
        $this->metadataUpdateThreshold = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|int $value
     * @return $this
     */
    public function sidLength($value): static
    {
        $this->_usedProperties['sidLength'] = true;
        $this->sidLength = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|int $value
     * @return $this
     */
    public function sidBitsPerCharacter($value): static
    {
        $this->_usedProperties['sidBitsPerCharacter'] = true;
        $this->sidBitsPerCharacter = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('enabled', $value)) {
            $this->_usedProperties['enabled'] = true;
            $this->enabled = $value['enabled'];
            unset($value['enabled']);
        }

        if (array_key_exists('storage_factory_id', $value)) {
            $this->_usedProperties['storageFactoryId'] = true;
            $this->storageFactoryId = $value['storage_factory_id'];
            unset($value['storage_factory_id']);
        }

        if (array_key_exists('handler_id', $value)) {
            $this->_usedProperties['handlerId'] = true;
            $this->handlerId = $value['handler_id'];
            unset($value['handler_id']);
        }

        if (array_key_exists('name', $value)) {
            $this->_usedProperties['name'] = true;
            $this->name = $value['name'];
            unset($value['name']);
        }

        if (array_key_exists('cookie_lifetime', $value)) {
            $this->_usedProperties['cookieLifetime'] = true;
            $this->cookieLifetime = $value['cookie_lifetime'];
            unset($value['cookie_lifetime']);
        }

        if (array_key_exists('cookie_path', $value)) {
            $this->_usedProperties['cookiePath'] = true;
            $this->cookiePath = $value['cookie_path'];
            unset($value['cookie_path']);
        }

        if (array_key_exists('cookie_domain', $value)) {
            $this->_usedProperties['cookieDomain'] = true;
            $this->cookieDomain = $value['cookie_domain'];
            unset($value['cookie_domain']);
        }

        if (array_key_exists('cookie_secure', $value)) {
            $this->_usedProperties['cookieSecure'] = true;
            $this->cookieSecure = $value['cookie_secure'];
            unset($value['cookie_secure']);
        }

        if (array_key_exists('cookie_httponly', $value)) {
            $this->_usedProperties['cookieHttponly'] = true;
            $this->cookieHttponly = $value['cookie_httponly'];
            unset($value['cookie_httponly']);
        }

        if (array_key_exists('cookie_samesite', $value)) {
            $this->_usedProperties['cookieSamesite'] = true;
            $this->cookieSamesite = $value['cookie_samesite'];
            unset($value['cookie_samesite']);
        }

        if (array_key_exists('use_cookies', $value)) {
            $this->_usedProperties['useCookies'] = true;
            $this->useCookies = $value['use_cookies'];
            unset($value['use_cookies']);
        }

        if (array_key_exists('gc_divisor', $value)) {
            $this->_usedProperties['gcDivisor'] = true;
            $this->gcDivisor = $value['gc_divisor'];
            unset($value['gc_divisor']);
        }

        if (array_key_exists('gc_probability', $value)) {
            $this->_usedProperties['gcProbability'] = true;
            $this->gcProbability = $value['gc_probability'];
            unset($value['gc_probability']);
        }

        if (array_key_exists('gc_maxlifetime', $value)) {
            $this->_usedProperties['gcMaxlifetime'] = true;
            $this->gcMaxlifetime = $value['gc_maxlifetime'];
            unset($value['gc_maxlifetime']);
        }

        if (array_key_exists('save_path', $value)) {
            $this->_usedProperties['savePath'] = true;
            $this->savePath = $value['save_path'];
            unset($value['save_path']);
        }

        if (array_key_exists('metadata_update_threshold', $value)) {
            $this->_usedProperties['metadataUpdateThreshold'] = true;
            $this->metadataUpdateThreshold = $value['metadata_update_threshold'];
            unset($value['metadata_update_threshold']);
        }

        if (array_key_exists('sid_length', $value)) {
            $this->_usedProperties['sidLength'] = true;
            $this->sidLength = $value['sid_length'];
            unset($value['sid_length']);
        }

        if (array_key_exists('sid_bits_per_character', $value)) {
            $this->_usedProperties['sidBitsPerCharacter'] = true;
            $this->sidBitsPerCharacter = $value['sid_bits_per_character'];
            unset($value['sid_bits_per_character']);
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
        if (isset($this->_usedProperties['storageFactoryId'])) {
            $output['storage_factory_id'] = $this->storageFactoryId;
        }
        if (isset($this->_usedProperties['handlerId'])) {
            $output['handler_id'] = $this->handlerId;
        }
        if (isset($this->_usedProperties['name'])) {
            $output['name'] = $this->name;
        }
        if (isset($this->_usedProperties['cookieLifetime'])) {
            $output['cookie_lifetime'] = $this->cookieLifetime;
        }
        if (isset($this->_usedProperties['cookiePath'])) {
            $output['cookie_path'] = $this->cookiePath;
        }
        if (isset($this->_usedProperties['cookieDomain'])) {
            $output['cookie_domain'] = $this->cookieDomain;
        }
        if (isset($this->_usedProperties['cookieSecure'])) {
            $output['cookie_secure'] = $this->cookieSecure;
        }
        if (isset($this->_usedProperties['cookieHttponly'])) {
            $output['cookie_httponly'] = $this->cookieHttponly;
        }
        if (isset($this->_usedProperties['cookieSamesite'])) {
            $output['cookie_samesite'] = $this->cookieSamesite;
        }
        if (isset($this->_usedProperties['useCookies'])) {
            $output['use_cookies'] = $this->useCookies;
        }
        if (isset($this->_usedProperties['gcDivisor'])) {
            $output['gc_divisor'] = $this->gcDivisor;
        }
        if (isset($this->_usedProperties['gcProbability'])) {
            $output['gc_probability'] = $this->gcProbability;
        }
        if (isset($this->_usedProperties['gcMaxlifetime'])) {
            $output['gc_maxlifetime'] = $this->gcMaxlifetime;
        }
        if (isset($this->_usedProperties['savePath'])) {
            $output['save_path'] = $this->savePath;
        }
        if (isset($this->_usedProperties['metadataUpdateThreshold'])) {
            $output['metadata_update_threshold'] = $this->metadataUpdateThreshold;
        }
        if (isset($this->_usedProperties['sidLength'])) {
            $output['sid_length'] = $this->sidLength;
        }
        if (isset($this->_usedProperties['sidBitsPerCharacter'])) {
            $output['sid_bits_per_character'] = $this->sidBitsPerCharacter;
        }

        return $output;
    }

}
