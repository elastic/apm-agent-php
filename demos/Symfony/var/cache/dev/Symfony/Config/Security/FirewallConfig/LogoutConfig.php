<?php

namespace Symfony\Config\Security\FirewallConfig;

require_once __DIR__.\DIRECTORY_SEPARATOR.'Logout'.\DIRECTORY_SEPARATOR.'DeleteCookieConfig.php';

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class LogoutConfig 
{
    private $csrfParameter;
    private $csrfTokenGenerator;
    private $csrfTokenId;
    private $path;
    private $target;
    private $invalidateSession;
    private $deleteCookies;
    private $_usedProperties = [];

    /**
     * @default '_csrf_token'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function csrfParameter($value): static
    {
        $this->_usedProperties['csrfParameter'] = true;
        $this->csrfParameter = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function csrfTokenGenerator($value): static
    {
        $this->_usedProperties['csrfTokenGenerator'] = true;
        $this->csrfTokenGenerator = $value;

        return $this;
    }

    /**
     * @default 'logout'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function csrfTokenId($value): static
    {
        $this->_usedProperties['csrfTokenId'] = true;
        $this->csrfTokenId = $value;

        return $this;
    }

    /**
     * @default '/logout'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function path($value): static
    {
        $this->_usedProperties['path'] = true;
        $this->path = $value;

        return $this;
    }

    /**
     * @default '/'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function target($value): static
    {
        $this->_usedProperties['target'] = true;
        $this->target = $value;

        return $this;
    }

    /**
     * @default true
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function invalidateSession($value): static
    {
        $this->_usedProperties['invalidateSession'] = true;
        $this->invalidateSession = $value;

        return $this;
    }

    /**
     * @return \Symfony\Config\Security\FirewallConfig\Logout\DeleteCookieConfig|$this
     */
    public function deleteCookie(string $name, mixed $value = []): \Symfony\Config\Security\FirewallConfig\Logout\DeleteCookieConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['deleteCookies'] = true;
            $this->deleteCookies[$name] = $value;

            return $this;
        }

        if (!isset($this->deleteCookies[$name]) || !$this->deleteCookies[$name] instanceof \Symfony\Config\Security\FirewallConfig\Logout\DeleteCookieConfig) {
            $this->_usedProperties['deleteCookies'] = true;
            $this->deleteCookies[$name] = new \Symfony\Config\Security\FirewallConfig\Logout\DeleteCookieConfig($value);
        } elseif (1 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "deleteCookie()" has already been initialized. You cannot pass values the second time you call deleteCookie().');
        }

        return $this->deleteCookies[$name];
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('csrf_parameter', $value)) {
            $this->_usedProperties['csrfParameter'] = true;
            $this->csrfParameter = $value['csrf_parameter'];
            unset($value['csrf_parameter']);
        }

        if (array_key_exists('csrf_token_generator', $value)) {
            $this->_usedProperties['csrfTokenGenerator'] = true;
            $this->csrfTokenGenerator = $value['csrf_token_generator'];
            unset($value['csrf_token_generator']);
        }

        if (array_key_exists('csrf_token_id', $value)) {
            $this->_usedProperties['csrfTokenId'] = true;
            $this->csrfTokenId = $value['csrf_token_id'];
            unset($value['csrf_token_id']);
        }

        if (array_key_exists('path', $value)) {
            $this->_usedProperties['path'] = true;
            $this->path = $value['path'];
            unset($value['path']);
        }

        if (array_key_exists('target', $value)) {
            $this->_usedProperties['target'] = true;
            $this->target = $value['target'];
            unset($value['target']);
        }

        if (array_key_exists('invalidate_session', $value)) {
            $this->_usedProperties['invalidateSession'] = true;
            $this->invalidateSession = $value['invalidate_session'];
            unset($value['invalidate_session']);
        }

        if (array_key_exists('delete_cookies', $value)) {
            $this->_usedProperties['deleteCookies'] = true;
            $this->deleteCookies = array_map(function ($v) { return \is_array($v) ? new \Symfony\Config\Security\FirewallConfig\Logout\DeleteCookieConfig($v) : $v; }, $value['delete_cookies']);
            unset($value['delete_cookies']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['csrfParameter'])) {
            $output['csrf_parameter'] = $this->csrfParameter;
        }
        if (isset($this->_usedProperties['csrfTokenGenerator'])) {
            $output['csrf_token_generator'] = $this->csrfTokenGenerator;
        }
        if (isset($this->_usedProperties['csrfTokenId'])) {
            $output['csrf_token_id'] = $this->csrfTokenId;
        }
        if (isset($this->_usedProperties['path'])) {
            $output['path'] = $this->path;
        }
        if (isset($this->_usedProperties['target'])) {
            $output['target'] = $this->target;
        }
        if (isset($this->_usedProperties['invalidateSession'])) {
            $output['invalidate_session'] = $this->invalidateSession;
        }
        if (isset($this->_usedProperties['deleteCookies'])) {
            $output['delete_cookies'] = array_map(function ($v) { return $v instanceof \Symfony\Config\Security\FirewallConfig\Logout\DeleteCookieConfig ? $v->toArray() : $v; }, $this->deleteCookies);
        }

        return $output;
    }

}
