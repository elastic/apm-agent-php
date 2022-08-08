<?php

namespace Symfony\Config\Framework;

require_once __DIR__.\DIRECTORY_SEPARATOR.'Mailer'.\DIRECTORY_SEPARATOR.'EnvelopeConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Mailer'.\DIRECTORY_SEPARATOR.'HeaderConfig.php';

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class MailerConfig 
{
    private $enabled;
    private $messageBus;
    private $dsn;
    private $transports;
    private $envelope;
    private $headers;
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
     * The message bus to use. Defaults to the default bus if the Messenger component is installed.
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function messageBus($value): static
    {
        $this->_usedProperties['messageBus'] = true;
        $this->messageBus = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function dsn($value): static
    {
        $this->_usedProperties['dsn'] = true;
        $this->dsn = $value;

        return $this;
    }

    /**
     * @return $this
     */
    public function transport(string $name, mixed $value): static
    {
        $this->_usedProperties['transports'] = true;
        $this->transports[$name] = $value;

        return $this;
    }

    /**
     * Mailer Envelope configuration
    */
    public function envelope(array $value = []): \Symfony\Config\Framework\Mailer\EnvelopeConfig
    {
        if (null === $this->envelope) {
            $this->_usedProperties['envelope'] = true;
            $this->envelope = new \Symfony\Config\Framework\Mailer\EnvelopeConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "envelope()" has already been initialized. You cannot pass values the second time you call envelope().');
        }

        return $this->envelope;
    }

    /**
     * @return \Symfony\Config\Framework\Mailer\HeaderConfig|$this
     */
    public function header(string $name, mixed $value = []): \Symfony\Config\Framework\Mailer\HeaderConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['headers'] = true;
            $this->headers[$name] = $value;

            return $this;
        }

        if (!isset($this->headers[$name]) || !$this->headers[$name] instanceof \Symfony\Config\Framework\Mailer\HeaderConfig) {
            $this->_usedProperties['headers'] = true;
            $this->headers[$name] = new \Symfony\Config\Framework\Mailer\HeaderConfig($value);
        } elseif (1 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "header()" has already been initialized. You cannot pass values the second time you call header().');
        }

        return $this->headers[$name];
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('enabled', $value)) {
            $this->_usedProperties['enabled'] = true;
            $this->enabled = $value['enabled'];
            unset($value['enabled']);
        }

        if (array_key_exists('message_bus', $value)) {
            $this->_usedProperties['messageBus'] = true;
            $this->messageBus = $value['message_bus'];
            unset($value['message_bus']);
        }

        if (array_key_exists('dsn', $value)) {
            $this->_usedProperties['dsn'] = true;
            $this->dsn = $value['dsn'];
            unset($value['dsn']);
        }

        if (array_key_exists('transports', $value)) {
            $this->_usedProperties['transports'] = true;
            $this->transports = $value['transports'];
            unset($value['transports']);
        }

        if (array_key_exists('envelope', $value)) {
            $this->_usedProperties['envelope'] = true;
            $this->envelope = new \Symfony\Config\Framework\Mailer\EnvelopeConfig($value['envelope']);
            unset($value['envelope']);
        }

        if (array_key_exists('headers', $value)) {
            $this->_usedProperties['headers'] = true;
            $this->headers = array_map(function ($v) { return \is_array($v) ? new \Symfony\Config\Framework\Mailer\HeaderConfig($v) : $v; }, $value['headers']);
            unset($value['headers']);
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
        if (isset($this->_usedProperties['messageBus'])) {
            $output['message_bus'] = $this->messageBus;
        }
        if (isset($this->_usedProperties['dsn'])) {
            $output['dsn'] = $this->dsn;
        }
        if (isset($this->_usedProperties['transports'])) {
            $output['transports'] = $this->transports;
        }
        if (isset($this->_usedProperties['envelope'])) {
            $output['envelope'] = $this->envelope->toArray();
        }
        if (isset($this->_usedProperties['headers'])) {
            $output['headers'] = array_map(function ($v) { return $v instanceof \Symfony\Config\Framework\Mailer\HeaderConfig ? $v->toArray() : $v; }, $this->headers);
        }

        return $output;
    }

}
