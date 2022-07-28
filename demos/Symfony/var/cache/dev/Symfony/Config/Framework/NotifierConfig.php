<?php

namespace Symfony\Config\Framework;

require_once __DIR__.\DIRECTORY_SEPARATOR.'Notifier'.\DIRECTORY_SEPARATOR.'AdminRecipientConfig.php';

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class NotifierConfig 
{
    private $enabled;
    private $chatterTransports;
    private $texterTransports;
    private $notificationOnFailedMessages;
    private $channelPolicy;
    private $adminRecipients;
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
     * @return $this
     */
    public function chatterTransport(string $name, mixed $value): static
    {
        $this->_usedProperties['chatterTransports'] = true;
        $this->chatterTransports[$name] = $value;

        return $this;
    }

    /**
     * @return $this
     */
    public function texterTransport(string $name, mixed $value): static
    {
        $this->_usedProperties['texterTransports'] = true;
        $this->texterTransports[$name] = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function notificationOnFailedMessages($value): static
    {
        $this->_usedProperties['notificationOnFailedMessages'] = true;
        $this->notificationOnFailedMessages = $value;

        return $this;
    }

    /**
     * @return $this
     */
    public function channelPolicy(string $name, mixed $value): static
    {
        $this->_usedProperties['channelPolicy'] = true;
        $this->channelPolicy[$name] = $value;

        return $this;
    }

    public function adminRecipient(array $value = []): \Symfony\Config\Framework\Notifier\AdminRecipientConfig
    {
        $this->_usedProperties['adminRecipients'] = true;

        return $this->adminRecipients[] = new \Symfony\Config\Framework\Notifier\AdminRecipientConfig($value);
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('enabled', $value)) {
            $this->_usedProperties['enabled'] = true;
            $this->enabled = $value['enabled'];
            unset($value['enabled']);
        }

        if (array_key_exists('chatter_transports', $value)) {
            $this->_usedProperties['chatterTransports'] = true;
            $this->chatterTransports = $value['chatter_transports'];
            unset($value['chatter_transports']);
        }

        if (array_key_exists('texter_transports', $value)) {
            $this->_usedProperties['texterTransports'] = true;
            $this->texterTransports = $value['texter_transports'];
            unset($value['texter_transports']);
        }

        if (array_key_exists('notification_on_failed_messages', $value)) {
            $this->_usedProperties['notificationOnFailedMessages'] = true;
            $this->notificationOnFailedMessages = $value['notification_on_failed_messages'];
            unset($value['notification_on_failed_messages']);
        }

        if (array_key_exists('channel_policy', $value)) {
            $this->_usedProperties['channelPolicy'] = true;
            $this->channelPolicy = $value['channel_policy'];
            unset($value['channel_policy']);
        }

        if (array_key_exists('admin_recipients', $value)) {
            $this->_usedProperties['adminRecipients'] = true;
            $this->adminRecipients = array_map(function ($v) { return new \Symfony\Config\Framework\Notifier\AdminRecipientConfig($v); }, $value['admin_recipients']);
            unset($value['admin_recipients']);
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
        if (isset($this->_usedProperties['chatterTransports'])) {
            $output['chatter_transports'] = $this->chatterTransports;
        }
        if (isset($this->_usedProperties['texterTransports'])) {
            $output['texter_transports'] = $this->texterTransports;
        }
        if (isset($this->_usedProperties['notificationOnFailedMessages'])) {
            $output['notification_on_failed_messages'] = $this->notificationOnFailedMessages;
        }
        if (isset($this->_usedProperties['channelPolicy'])) {
            $output['channel_policy'] = $this->channelPolicy;
        }
        if (isset($this->_usedProperties['adminRecipients'])) {
            $output['admin_recipients'] = array_map(function ($v) { return $v->toArray(); }, $this->adminRecipients);
        }

        return $output;
    }

}
