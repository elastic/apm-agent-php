<?php

namespace Symfony\Config\Framework;

require_once __DIR__.\DIRECTORY_SEPARATOR.'Messenger'.\DIRECTORY_SEPARATOR.'RoutingConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Messenger'.\DIRECTORY_SEPARATOR.'SerializerConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Messenger'.\DIRECTORY_SEPARATOR.'TransportConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Messenger'.\DIRECTORY_SEPARATOR.'BusConfig.php';

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class MessengerConfig 
{
    private $enabled;
    private $routing;
    private $serializer;
    private $transports;
    private $failureTransport;
    private $resetOnMessage;
    private $defaultBus;
    private $buses;
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
     * @return \Symfony\Config\Framework\Messenger\RoutingConfig|$this
     */
    public function routing(string $message_class, mixed $value = []): \Symfony\Config\Framework\Messenger\RoutingConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['routing'] = true;
            $this->routing[$message_class] = $value;

            return $this;
        }

        if (!isset($this->routing[$message_class]) || !$this->routing[$message_class] instanceof \Symfony\Config\Framework\Messenger\RoutingConfig) {
            $this->_usedProperties['routing'] = true;
            $this->routing[$message_class] = new \Symfony\Config\Framework\Messenger\RoutingConfig($value);
        } elseif (1 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "routing()" has already been initialized. You cannot pass values the second time you call routing().');
        }

        return $this->routing[$message_class];
    }

    /**
     * @default {"default_serializer":"messenger.transport.native_php_serializer","symfony_serializer":{"format":"json","context":[]}}
    */
    public function serializer(array $value = []): \Symfony\Config\Framework\Messenger\SerializerConfig
    {
        if (null === $this->serializer) {
            $this->_usedProperties['serializer'] = true;
            $this->serializer = new \Symfony\Config\Framework\Messenger\SerializerConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "serializer()" has already been initialized. You cannot pass values the second time you call serializer().');
        }

        return $this->serializer;
    }

    /**
     * @return \Symfony\Config\Framework\Messenger\TransportConfig|$this
     */
    public function transport(string $name, mixed $value = []): \Symfony\Config\Framework\Messenger\TransportConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['transports'] = true;
            $this->transports[$name] = $value;

            return $this;
        }

        if (!isset($this->transports[$name]) || !$this->transports[$name] instanceof \Symfony\Config\Framework\Messenger\TransportConfig) {
            $this->_usedProperties['transports'] = true;
            $this->transports[$name] = new \Symfony\Config\Framework\Messenger\TransportConfig($value);
        } elseif (1 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "transport()" has already been initialized. You cannot pass values the second time you call transport().');
        }

        return $this->transports[$name];
    }

    /**
     * Transport name to send failed messages to (after all retries have failed).
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function failureTransport($value): static
    {
        $this->_usedProperties['failureTransport'] = true;
        $this->failureTransport = $value;

        return $this;
    }

    /**
     * Reset container services after each message.
     * @default true
     * @param ParamConfigurator|bool $value
     * @deprecated Option "reset_on_message" at "messenger" is deprecated. It does nothing and will be removed in version 7.0.
     * @return $this
     */
    public function resetOnMessage($value): static
    {
        $this->_usedProperties['resetOnMessage'] = true;
        $this->resetOnMessage = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function defaultBus($value): static
    {
        $this->_usedProperties['defaultBus'] = true;
        $this->defaultBus = $value;

        return $this;
    }

    /**
     * @default {"messenger.bus.default":{"default_middleware":true,"middleware":[]}}
    */
    public function bus(string $name, array $value = []): \Symfony\Config\Framework\Messenger\BusConfig
    {
        if (!isset($this->buses[$name])) {
            $this->_usedProperties['buses'] = true;
            $this->buses[$name] = new \Symfony\Config\Framework\Messenger\BusConfig($value);
        } elseif (1 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "bus()" has already been initialized. You cannot pass values the second time you call bus().');
        }

        return $this->buses[$name];
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('enabled', $value)) {
            $this->_usedProperties['enabled'] = true;
            $this->enabled = $value['enabled'];
            unset($value['enabled']);
        }

        if (array_key_exists('routing', $value)) {
            $this->_usedProperties['routing'] = true;
            $this->routing = array_map(function ($v) { return \is_array($v) ? new \Symfony\Config\Framework\Messenger\RoutingConfig($v) : $v; }, $value['routing']);
            unset($value['routing']);
        }

        if (array_key_exists('serializer', $value)) {
            $this->_usedProperties['serializer'] = true;
            $this->serializer = new \Symfony\Config\Framework\Messenger\SerializerConfig($value['serializer']);
            unset($value['serializer']);
        }

        if (array_key_exists('transports', $value)) {
            $this->_usedProperties['transports'] = true;
            $this->transports = array_map(function ($v) { return \is_array($v) ? new \Symfony\Config\Framework\Messenger\TransportConfig($v) : $v; }, $value['transports']);
            unset($value['transports']);
        }

        if (array_key_exists('failure_transport', $value)) {
            $this->_usedProperties['failureTransport'] = true;
            $this->failureTransport = $value['failure_transport'];
            unset($value['failure_transport']);
        }

        if (array_key_exists('reset_on_message', $value)) {
            $this->_usedProperties['resetOnMessage'] = true;
            $this->resetOnMessage = $value['reset_on_message'];
            unset($value['reset_on_message']);
        }

        if (array_key_exists('default_bus', $value)) {
            $this->_usedProperties['defaultBus'] = true;
            $this->defaultBus = $value['default_bus'];
            unset($value['default_bus']);
        }

        if (array_key_exists('buses', $value)) {
            $this->_usedProperties['buses'] = true;
            $this->buses = array_map(function ($v) { return new \Symfony\Config\Framework\Messenger\BusConfig($v); }, $value['buses']);
            unset($value['buses']);
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
        if (isset($this->_usedProperties['routing'])) {
            $output['routing'] = array_map(function ($v) { return $v instanceof \Symfony\Config\Framework\Messenger\RoutingConfig ? $v->toArray() : $v; }, $this->routing);
        }
        if (isset($this->_usedProperties['serializer'])) {
            $output['serializer'] = $this->serializer->toArray();
        }
        if (isset($this->_usedProperties['transports'])) {
            $output['transports'] = array_map(function ($v) { return $v instanceof \Symfony\Config\Framework\Messenger\TransportConfig ? $v->toArray() : $v; }, $this->transports);
        }
        if (isset($this->_usedProperties['failureTransport'])) {
            $output['failure_transport'] = $this->failureTransport;
        }
        if (isset($this->_usedProperties['resetOnMessage'])) {
            $output['reset_on_message'] = $this->resetOnMessage;
        }
        if (isset($this->_usedProperties['defaultBus'])) {
            $output['default_bus'] = $this->defaultBus;
        }
        if (isset($this->_usedProperties['buses'])) {
            $output['buses'] = array_map(function ($v) { return $v->toArray(); }, $this->buses);
        }

        return $output;
    }

}
