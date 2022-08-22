<?php

namespace Symfony\Config\Framework\Messenger;

require_once __DIR__.\DIRECTORY_SEPARATOR.'BusConfig'.\DIRECTORY_SEPARATOR.'MiddlewareConfig.php';

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class BusConfig 
{
    private $defaultMiddleware;
    private $middleware;
    private $_usedProperties = [];

    /**
     * @default true
     * @param ParamConfigurator|true|false|'allow_no_handlers' $value
     * @return $this
     */
    public function defaultMiddleware($value): static
    {
        $this->_usedProperties['defaultMiddleware'] = true;
        $this->defaultMiddleware = $value;

        return $this;
    }

    /**
     * @return \Symfony\Config\Framework\Messenger\BusConfig\MiddlewareConfig|$this
     */
    public function middleware(mixed $value = []): \Symfony\Config\Framework\Messenger\BusConfig\MiddlewareConfig|static
    {
        $this->_usedProperties['middleware'] = true;
        if (!\is_array($value)) {
            $this->middleware[] = $value;

            return $this;
        }

        return $this->middleware[] = new \Symfony\Config\Framework\Messenger\BusConfig\MiddlewareConfig($value);
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('default_middleware', $value)) {
            $this->_usedProperties['defaultMiddleware'] = true;
            $this->defaultMiddleware = $value['default_middleware'];
            unset($value['default_middleware']);
        }

        if (array_key_exists('middleware', $value)) {
            $this->_usedProperties['middleware'] = true;
            $this->middleware = array_map(function ($v) { return \is_array($v) ? new \Symfony\Config\Framework\Messenger\BusConfig\MiddlewareConfig($v) : $v; }, $value['middleware']);
            unset($value['middleware']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['defaultMiddleware'])) {
            $output['default_middleware'] = $this->defaultMiddleware;
        }
        if (isset($this->_usedProperties['middleware'])) {
            $output['middleware'] = array_map(function ($v) { return $v instanceof \Symfony\Config\Framework\Messenger\BusConfig\MiddlewareConfig ? $v->toArray() : $v; }, $this->middleware);
        }

        return $output;
    }

}
