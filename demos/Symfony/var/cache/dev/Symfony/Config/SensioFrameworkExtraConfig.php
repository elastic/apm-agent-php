<?php

namespace Symfony\Config;

require_once __DIR__.\DIRECTORY_SEPARATOR.'SensioFrameworkExtra'.\DIRECTORY_SEPARATOR.'RouterConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'SensioFrameworkExtra'.\DIRECTORY_SEPARATOR.'RequestConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'SensioFrameworkExtra'.\DIRECTORY_SEPARATOR.'ViewConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'SensioFrameworkExtra'.\DIRECTORY_SEPARATOR.'CacheConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'SensioFrameworkExtra'.\DIRECTORY_SEPARATOR.'SecurityConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'SensioFrameworkExtra'.\DIRECTORY_SEPARATOR.'TemplatingConfig.php';

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class SensioFrameworkExtraConfig implements \Symfony\Component\Config\Builder\ConfigBuilderInterface
{
    private $router;
    private $request;
    private $view;
    private $cache;
    private $security;
    private $templating;
    private $_usedProperties = [];

    /**
     * @default {"annotations":true}
    */
    public function router(array $value = []): \Symfony\Config\SensioFrameworkExtra\RouterConfig
    {
        if (null === $this->router) {
            $this->_usedProperties['router'] = true;
            $this->router = new \Symfony\Config\SensioFrameworkExtra\RouterConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "router()" has already been initialized. You cannot pass values the second time you call router().');
        }

        return $this->router;
    }

    /**
     * @default {"converters":true,"auto_convert":true,"disable":[]}
    */
    public function request(array $value = []): \Symfony\Config\SensioFrameworkExtra\RequestConfig
    {
        if (null === $this->request) {
            $this->_usedProperties['request'] = true;
            $this->request = new \Symfony\Config\SensioFrameworkExtra\RequestConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "request()" has already been initialized. You cannot pass values the second time you call request().');
        }

        return $this->request;
    }

    /**
     * @default {"annotations":true}
    */
    public function view(array $value = []): \Symfony\Config\SensioFrameworkExtra\ViewConfig
    {
        if (null === $this->view) {
            $this->_usedProperties['view'] = true;
            $this->view = new \Symfony\Config\SensioFrameworkExtra\ViewConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "view()" has already been initialized. You cannot pass values the second time you call view().');
        }

        return $this->view;
    }

    /**
     * @default {"annotations":true}
    */
    public function cache(array $value = []): \Symfony\Config\SensioFrameworkExtra\CacheConfig
    {
        if (null === $this->cache) {
            $this->_usedProperties['cache'] = true;
            $this->cache = new \Symfony\Config\SensioFrameworkExtra\CacheConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "cache()" has already been initialized. You cannot pass values the second time you call cache().');
        }

        return $this->cache;
    }

    /**
     * @default {"annotations":true,"expression_language":"sensio_framework_extra.security.expression_language.default"}
    */
    public function security(array $value = []): \Symfony\Config\SensioFrameworkExtra\SecurityConfig
    {
        if (null === $this->security) {
            $this->_usedProperties['security'] = true;
            $this->security = new \Symfony\Config\SensioFrameworkExtra\SecurityConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "security()" has already been initialized. You cannot pass values the second time you call security().');
        }

        return $this->security;
    }

    public function templating(array $value = []): \Symfony\Config\SensioFrameworkExtra\TemplatingConfig
    {
        if (null === $this->templating) {
            $this->_usedProperties['templating'] = true;
            $this->templating = new \Symfony\Config\SensioFrameworkExtra\TemplatingConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "templating()" has already been initialized. You cannot pass values the second time you call templating().');
        }

        return $this->templating;
    }

    public function getExtensionAlias(): string
    {
        return 'sensio_framework_extra';
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('router', $value)) {
            $this->_usedProperties['router'] = true;
            $this->router = new \Symfony\Config\SensioFrameworkExtra\RouterConfig($value['router']);
            unset($value['router']);
        }

        if (array_key_exists('request', $value)) {
            $this->_usedProperties['request'] = true;
            $this->request = new \Symfony\Config\SensioFrameworkExtra\RequestConfig($value['request']);
            unset($value['request']);
        }

        if (array_key_exists('view', $value)) {
            $this->_usedProperties['view'] = true;
            $this->view = new \Symfony\Config\SensioFrameworkExtra\ViewConfig($value['view']);
            unset($value['view']);
        }

        if (array_key_exists('cache', $value)) {
            $this->_usedProperties['cache'] = true;
            $this->cache = new \Symfony\Config\SensioFrameworkExtra\CacheConfig($value['cache']);
            unset($value['cache']);
        }

        if (array_key_exists('security', $value)) {
            $this->_usedProperties['security'] = true;
            $this->security = new \Symfony\Config\SensioFrameworkExtra\SecurityConfig($value['security']);
            unset($value['security']);
        }

        if (array_key_exists('templating', $value)) {
            $this->_usedProperties['templating'] = true;
            $this->templating = new \Symfony\Config\SensioFrameworkExtra\TemplatingConfig($value['templating']);
            unset($value['templating']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['router'])) {
            $output['router'] = $this->router->toArray();
        }
        if (isset($this->_usedProperties['request'])) {
            $output['request'] = $this->request->toArray();
        }
        if (isset($this->_usedProperties['view'])) {
            $output['view'] = $this->view->toArray();
        }
        if (isset($this->_usedProperties['cache'])) {
            $output['cache'] = $this->cache->toArray();
        }
        if (isset($this->_usedProperties['security'])) {
            $output['security'] = $this->security->toArray();
        }
        if (isset($this->_usedProperties['templating'])) {
            $output['templating'] = $this->templating->toArray();
        }

        return $output;
    }

}
