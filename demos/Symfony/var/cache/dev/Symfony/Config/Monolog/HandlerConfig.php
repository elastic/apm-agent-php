<?php

namespace Symfony\Config\Monolog;

require_once __DIR__.\DIRECTORY_SEPARATOR.'HandlerConfig'.\DIRECTORY_SEPARATOR.'ProcessPsr3MessagesConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'HandlerConfig'.\DIRECTORY_SEPARATOR.'ExcludedHttpCodeConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'HandlerConfig'.\DIRECTORY_SEPARATOR.'PublisherConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'HandlerConfig'.\DIRECTORY_SEPARATOR.'MongoConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'HandlerConfig'.\DIRECTORY_SEPARATOR.'ElasticsearchConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'HandlerConfig'.\DIRECTORY_SEPARATOR.'RedisConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'HandlerConfig'.\DIRECTORY_SEPARATOR.'PredisConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'HandlerConfig'.\DIRECTORY_SEPARATOR.'EmailPrototypeConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'HandlerConfig'.\DIRECTORY_SEPARATOR.'VerbosityLevelsConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'HandlerConfig'.\DIRECTORY_SEPARATOR.'ChannelsConfig.php';

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class HandlerConfig 
{
    private $type;
    private $id;
    private $priority;
    private $level;
    private $bubble;
    private $appName;
    private $fillExtraContext;
    private $includeStacktraces;
    private $processPsr3Messages;
    private $path;
    private $filePermission;
    private $useLocking;
    private $filenameFormat;
    private $dateFormat;
    private $ident;
    private $logopts;
    private $facility;
    private $maxFiles;
    private $actionLevel;
    private $activationStrategy;
    private $stopBuffering;
    private $passthruLevel;
    private $excluded404s;
    private $excludedHttpCodes;
    private $acceptedLevels;
    private $minLevel;
    private $maxLevel;
    private $bufferSize;
    private $flushOnOverflow;
    private $handler;
    private $url;
    private $exchange;
    private $exchangeName;
    private $room;
    private $messageFormat;
    private $apiVersion;
    private $channel;
    private $botName;
    private $useAttachment;
    private $useShortAttachment;
    private $includeExtra;
    private $iconEmoji;
    private $webhookUrl;
    private $team;
    private $notify;
    private $nickname;
    private $token;
    private $region;
    private $source;
    private $useSsl;
    private $user;
    private $title;
    private $host;
    private $port;
    private $config;
    private $members;
    private $connectionString;
    private $timeout;
    private $time;
    private $deduplicationLevel;
    private $store;
    private $connectionTimeout;
    private $persistent;
    private $dsn;
    private $hubId;
    private $clientId;
    private $autoLogStacks;
    private $release;
    private $environment;
    private $messageType;
    private $parseMode;
    private $disableWebpagePreview;
    private $disableNotification;
    private $splitLongMessages;
    private $delayBetweenMessages;
    private $tags;
    private $consoleFormaterOptions;
    private $consoleFormatterOptions;
    private $formatter;
    private $nested;
    private $publisher;
    private $mongo;
    private $elasticsearch;
    private $index;
    private $documentType;
    private $ignoreError;
    private $redis;
    private $predis;
    private $fromEmail;
    private $toEmail;
    private $subject;
    private $contentType;
    private $headers;
    private $mailer;
    private $emailPrototype;
    private $lazy;
    private $verbosityLevels;
    private $channels;
    private $_usedProperties = [];

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function type($value): static
    {
        $this->_usedProperties['type'] = true;
        $this->type = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function id($value): static
    {
        $this->_usedProperties['id'] = true;
        $this->id = $value;

        return $this;
    }

    /**
     * @default 0
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function priority($value): static
    {
        $this->_usedProperties['priority'] = true;
        $this->priority = $value;

        return $this;
    }

    /**
     * @default 'DEBUG'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function level($value): static
    {
        $this->_usedProperties['level'] = true;
        $this->level = $value;

        return $this;
    }

    /**
     * @default true
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function bubble($value): static
    {
        $this->_usedProperties['bubble'] = true;
        $this->bubble = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function appName($value): static
    {
        $this->_usedProperties['appName'] = true;
        $this->appName = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function fillExtraContext($value): static
    {
        $this->_usedProperties['fillExtraContext'] = true;
        $this->fillExtraContext = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function includeStacktraces($value): static
    {
        $this->_usedProperties['includeStacktraces'] = true;
        $this->includeStacktraces = $value;

        return $this;
    }

    /**
     * @default {"enabled":null}
     * @return \Symfony\Config\Monolog\HandlerConfig\ProcessPsr3MessagesConfig|$this
     */
    public function processPsr3Messages(mixed $value = []): \Symfony\Config\Monolog\HandlerConfig\ProcessPsr3MessagesConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['processPsr3Messages'] = true;
            $this->processPsr3Messages = $value;

            return $this;
        }

        if (!$this->processPsr3Messages instanceof \Symfony\Config\Monolog\HandlerConfig\ProcessPsr3MessagesConfig) {
            $this->_usedProperties['processPsr3Messages'] = true;
            $this->processPsr3Messages = new \Symfony\Config\Monolog\HandlerConfig\ProcessPsr3MessagesConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "processPsr3Messages()" has already been initialized. You cannot pass values the second time you call processPsr3Messages().');
        }

        return $this->processPsr3Messages;
    }

    /**
     * @default '%kernel.logs_dir%/%kernel.environment%.log'
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
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function filePermission($value): static
    {
        $this->_usedProperties['filePermission'] = true;
        $this->filePermission = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function useLocking($value): static
    {
        $this->_usedProperties['useLocking'] = true;
        $this->useLocking = $value;

        return $this;
    }

    /**
     * @default '{filename}-{date}'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function filenameFormat($value): static
    {
        $this->_usedProperties['filenameFormat'] = true;
        $this->filenameFormat = $value;

        return $this;
    }

    /**
     * @default 'Y-m-d'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function dateFormat($value): static
    {
        $this->_usedProperties['dateFormat'] = true;
        $this->dateFormat = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function ident($value): static
    {
        $this->_usedProperties['ident'] = true;
        $this->ident = $value;

        return $this;
    }

    /**
     * @default 1
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function logopts($value): static
    {
        $this->_usedProperties['logopts'] = true;
        $this->logopts = $value;

        return $this;
    }

    /**
     * @default 'user'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function facility($value): static
    {
        $this->_usedProperties['facility'] = true;
        $this->facility = $value;

        return $this;
    }

    /**
     * @default 0
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function maxFiles($value): static
    {
        $this->_usedProperties['maxFiles'] = true;
        $this->maxFiles = $value;

        return $this;
    }

    /**
     * @default 'WARNING'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function actionLevel($value): static
    {
        $this->_usedProperties['actionLevel'] = true;
        $this->actionLevel = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function activationStrategy($value): static
    {
        $this->_usedProperties['activationStrategy'] = true;
        $this->activationStrategy = $value;

        return $this;
    }

    /**
     * @default true
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function stopBuffering($value): static
    {
        $this->_usedProperties['stopBuffering'] = true;
        $this->stopBuffering = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function passthruLevel($value): static
    {
        $this->_usedProperties['passthruLevel'] = true;
        $this->passthruLevel = $value;

        return $this;
    }

    /**
     * @param ParamConfigurator|list<ParamConfigurator|mixed> $value
     *
     * @return $this
     */
    public function excluded404s(ParamConfigurator|array $value): static
    {
        $this->_usedProperties['excluded404s'] = true;
        $this->excluded404s = $value;

        return $this;
    }

    /**
     * @return \Symfony\Config\Monolog\HandlerConfig\ExcludedHttpCodeConfig|$this
     */
    public function excludedHttpCode(mixed $value = []): \Symfony\Config\Monolog\HandlerConfig\ExcludedHttpCodeConfig|static
    {
        $this->_usedProperties['excludedHttpCodes'] = true;
        if (!\is_array($value)) {
            $this->excludedHttpCodes[] = $value;

            return $this;
        }

        return $this->excludedHttpCodes[] = new \Symfony\Config\Monolog\HandlerConfig\ExcludedHttpCodeConfig($value);
    }

    /**
     * @param ParamConfigurator|list<ParamConfigurator|mixed> $value
     *
     * @return $this
     */
    public function acceptedLevels(ParamConfigurator|array $value): static
    {
        $this->_usedProperties['acceptedLevels'] = true;
        $this->acceptedLevels = $value;

        return $this;
    }

    /**
     * @default 'DEBUG'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function minLevel($value): static
    {
        $this->_usedProperties['minLevel'] = true;
        $this->minLevel = $value;

        return $this;
    }

    /**
     * @default 'EMERGENCY'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function maxLevel($value): static
    {
        $this->_usedProperties['maxLevel'] = true;
        $this->maxLevel = $value;

        return $this;
    }

    /**
     * @default 0
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function bufferSize($value): static
    {
        $this->_usedProperties['bufferSize'] = true;
        $this->bufferSize = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function flushOnOverflow($value): static
    {
        $this->_usedProperties['flushOnOverflow'] = true;
        $this->flushOnOverflow = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function handler($value): static
    {
        $this->_usedProperties['handler'] = true;
        $this->handler = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function url($value): static
    {
        $this->_usedProperties['url'] = true;
        $this->url = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function exchange($value): static
    {
        $this->_usedProperties['exchange'] = true;
        $this->exchange = $value;

        return $this;
    }

    /**
     * @default 'log'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function exchangeName($value): static
    {
        $this->_usedProperties['exchangeName'] = true;
        $this->exchangeName = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function room($value): static
    {
        $this->_usedProperties['room'] = true;
        $this->room = $value;

        return $this;
    }

    /**
     * @default 'text'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function messageFormat($value): static
    {
        $this->_usedProperties['messageFormat'] = true;
        $this->messageFormat = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function apiVersion($value): static
    {
        $this->_usedProperties['apiVersion'] = true;
        $this->apiVersion = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function channel($value): static
    {
        $this->_usedProperties['channel'] = true;
        $this->channel = $value;

        return $this;
    }

    /**
     * @default 'Monolog'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function botName($value): static
    {
        $this->_usedProperties['botName'] = true;
        $this->botName = $value;

        return $this;
    }

    /**
     * @default true
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function useAttachment($value): static
    {
        $this->_usedProperties['useAttachment'] = true;
        $this->useAttachment = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function useShortAttachment($value): static
    {
        $this->_usedProperties['useShortAttachment'] = true;
        $this->useShortAttachment = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function includeExtra($value): static
    {
        $this->_usedProperties['includeExtra'] = true;
        $this->includeExtra = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function iconEmoji($value): static
    {
        $this->_usedProperties['iconEmoji'] = true;
        $this->iconEmoji = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function webhookUrl($value): static
    {
        $this->_usedProperties['webhookUrl'] = true;
        $this->webhookUrl = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function team($value): static
    {
        $this->_usedProperties['team'] = true;
        $this->team = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function notify($value): static
    {
        $this->_usedProperties['notify'] = true;
        $this->notify = $value;

        return $this;
    }

    /**
     * @default 'Monolog'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function nickname($value): static
    {
        $this->_usedProperties['nickname'] = true;
        $this->nickname = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function token($value): static
    {
        $this->_usedProperties['token'] = true;
        $this->token = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function region($value): static
    {
        $this->_usedProperties['region'] = true;
        $this->region = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function source($value): static
    {
        $this->_usedProperties['source'] = true;
        $this->source = $value;

        return $this;
    }

    /**
     * @default true
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function useSsl($value): static
    {
        $this->_usedProperties['useSsl'] = true;
        $this->useSsl = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     *
     * @return $this
     */
    public function user(mixed $value): static
    {
        $this->_usedProperties['user'] = true;
        $this->user = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function title($value): static
    {
        $this->_usedProperties['title'] = true;
        $this->title = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function host($value): static
    {
        $this->_usedProperties['host'] = true;
        $this->host = $value;

        return $this;
    }

    /**
     * @default 514
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function port($value): static
    {
        $this->_usedProperties['port'] = true;
        $this->port = $value;

        return $this;
    }

    /**
     * @param ParamConfigurator|list<ParamConfigurator|mixed> $value
     *
     * @return $this
     */
    public function config(ParamConfigurator|array $value): static
    {
        $this->_usedProperties['config'] = true;
        $this->config = $value;

        return $this;
    }

    /**
     * @param ParamConfigurator|list<ParamConfigurator|mixed> $value
     *
     * @return $this
     */
    public function members(ParamConfigurator|array $value): static
    {
        $this->_usedProperties['members'] = true;
        $this->members = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function connectionString($value): static
    {
        $this->_usedProperties['connectionString'] = true;
        $this->connectionString = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function timeout($value): static
    {
        $this->_usedProperties['timeout'] = true;
        $this->timeout = $value;

        return $this;
    }

    /**
     * @default 60
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function time($value): static
    {
        $this->_usedProperties['time'] = true;
        $this->time = $value;

        return $this;
    }

    /**
     * @default 400
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function deduplicationLevel($value): static
    {
        $this->_usedProperties['deduplicationLevel'] = true;
        $this->deduplicationLevel = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function store($value): static
    {
        $this->_usedProperties['store'] = true;
        $this->store = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function connectionTimeout($value): static
    {
        $this->_usedProperties['connectionTimeout'] = true;
        $this->connectionTimeout = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function persistent($value): static
    {
        $this->_usedProperties['persistent'] = true;
        $this->persistent = $value;

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
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function hubId($value): static
    {
        $this->_usedProperties['hubId'] = true;
        $this->hubId = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function clientId($value): static
    {
        $this->_usedProperties['clientId'] = true;
        $this->clientId = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function autoLogStacks($value): static
    {
        $this->_usedProperties['autoLogStacks'] = true;
        $this->autoLogStacks = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function release($value): static
    {
        $this->_usedProperties['release'] = true;
        $this->release = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function environment($value): static
    {
        $this->_usedProperties['environment'] = true;
        $this->environment = $value;

        return $this;
    }

    /**
     * @default 0
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function messageType($value): static
    {
        $this->_usedProperties['messageType'] = true;
        $this->messageType = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function parseMode($value): static
    {
        $this->_usedProperties['parseMode'] = true;
        $this->parseMode = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function disableWebpagePreview($value): static
    {
        $this->_usedProperties['disableWebpagePreview'] = true;
        $this->disableWebpagePreview = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function disableNotification($value): static
    {
        $this->_usedProperties['disableNotification'] = true;
        $this->disableNotification = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function splitLongMessages($value): static
    {
        $this->_usedProperties['splitLongMessages'] = true;
        $this->splitLongMessages = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function delayBetweenMessages($value): static
    {
        $this->_usedProperties['delayBetweenMessages'] = true;
        $this->delayBetweenMessages = $value;

        return $this;
    }

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function tags(mixed $value): static
    {
        $this->_usedProperties['tags'] = true;
        $this->tags = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @deprecated ".console_formater_options" is deprecated, use ".console_formatter_options" instead.
     *
     * @return $this
     */
    public function consoleFormaterOptions(mixed $value): static
    {
        $this->_usedProperties['consoleFormaterOptions'] = true;
        $this->consoleFormaterOptions = $value;

        return $this;
    }

    /**
     * @default array (
    )
     * @param ParamConfigurator|mixed $value
     *
     * @return $this
     */
    public function consoleFormatterOptions(mixed $value = array (
    )): static
    {
        $this->_usedProperties['consoleFormatterOptions'] = true;
        $this->consoleFormatterOptions = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function formatter($value): static
    {
        $this->_usedProperties['formatter'] = true;
        $this->formatter = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function nested($value): static
    {
        $this->_usedProperties['nested'] = true;
        $this->nested = $value;

        return $this;
    }

    /**
     * @return \Symfony\Config\Monolog\HandlerConfig\PublisherConfig|$this
     */
    public function publisher(mixed $value = []): \Symfony\Config\Monolog\HandlerConfig\PublisherConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['publisher'] = true;
            $this->publisher = $value;

            return $this;
        }

        if (!$this->publisher instanceof \Symfony\Config\Monolog\HandlerConfig\PublisherConfig) {
            $this->_usedProperties['publisher'] = true;
            $this->publisher = new \Symfony\Config\Monolog\HandlerConfig\PublisherConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "publisher()" has already been initialized. You cannot pass values the second time you call publisher().');
        }

        return $this->publisher;
    }

    /**
     * @return \Symfony\Config\Monolog\HandlerConfig\MongoConfig|$this
     */
    public function mongo(mixed $value = []): \Symfony\Config\Monolog\HandlerConfig\MongoConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['mongo'] = true;
            $this->mongo = $value;

            return $this;
        }

        if (!$this->mongo instanceof \Symfony\Config\Monolog\HandlerConfig\MongoConfig) {
            $this->_usedProperties['mongo'] = true;
            $this->mongo = new \Symfony\Config\Monolog\HandlerConfig\MongoConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "mongo()" has already been initialized. You cannot pass values the second time you call mongo().');
        }

        return $this->mongo;
    }

    /**
     * @return \Symfony\Config\Monolog\HandlerConfig\ElasticsearchConfig|$this
     */
    public function elasticsearch(mixed $value = []): \Symfony\Config\Monolog\HandlerConfig\ElasticsearchConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['elasticsearch'] = true;
            $this->elasticsearch = $value;

            return $this;
        }

        if (!$this->elasticsearch instanceof \Symfony\Config\Monolog\HandlerConfig\ElasticsearchConfig) {
            $this->_usedProperties['elasticsearch'] = true;
            $this->elasticsearch = new \Symfony\Config\Monolog\HandlerConfig\ElasticsearchConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "elasticsearch()" has already been initialized. You cannot pass values the second time you call elasticsearch().');
        }

        return $this->elasticsearch;
    }

    /**
     * @default 'monolog'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function index($value): static
    {
        $this->_usedProperties['index'] = true;
        $this->index = $value;

        return $this;
    }

    /**
     * @default 'logs'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function documentType($value): static
    {
        $this->_usedProperties['documentType'] = true;
        $this->documentType = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function ignoreError($value): static
    {
        $this->_usedProperties['ignoreError'] = true;
        $this->ignoreError = $value;

        return $this;
    }

    /**
     * @return \Symfony\Config\Monolog\HandlerConfig\RedisConfig|$this
     */
    public function redis(mixed $value = []): \Symfony\Config\Monolog\HandlerConfig\RedisConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['redis'] = true;
            $this->redis = $value;

            return $this;
        }

        if (!$this->redis instanceof \Symfony\Config\Monolog\HandlerConfig\RedisConfig) {
            $this->_usedProperties['redis'] = true;
            $this->redis = new \Symfony\Config\Monolog\HandlerConfig\RedisConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "redis()" has already been initialized. You cannot pass values the second time you call redis().');
        }

        return $this->redis;
    }

    /**
     * @return \Symfony\Config\Monolog\HandlerConfig\PredisConfig|$this
     */
    public function predis(mixed $value = []): \Symfony\Config\Monolog\HandlerConfig\PredisConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['predis'] = true;
            $this->predis = $value;

            return $this;
        }

        if (!$this->predis instanceof \Symfony\Config\Monolog\HandlerConfig\PredisConfig) {
            $this->_usedProperties['predis'] = true;
            $this->predis = new \Symfony\Config\Monolog\HandlerConfig\PredisConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "predis()" has already been initialized. You cannot pass values the second time you call predis().');
        }

        return $this->predis;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function fromEmail($value): static
    {
        $this->_usedProperties['fromEmail'] = true;
        $this->fromEmail = $value;

        return $this;
    }

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function toEmail(mixed $value): static
    {
        $this->_usedProperties['toEmail'] = true;
        $this->toEmail = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function subject($value): static
    {
        $this->_usedProperties['subject'] = true;
        $this->subject = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function contentType($value): static
    {
        $this->_usedProperties['contentType'] = true;
        $this->contentType = $value;

        return $this;
    }

    /**
     * @param ParamConfigurator|list<ParamConfigurator|mixed> $value
     *
     * @return $this
     */
    public function headers(ParamConfigurator|array $value): static
    {
        $this->_usedProperties['headers'] = true;
        $this->headers = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function mailer($value): static
    {
        $this->_usedProperties['mailer'] = true;
        $this->mailer = $value;

        return $this;
    }

    /**
     * @return \Symfony\Config\Monolog\HandlerConfig\EmailPrototypeConfig|$this
     */
    public function emailPrototype(mixed $value = []): \Symfony\Config\Monolog\HandlerConfig\EmailPrototypeConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['emailPrototype'] = true;
            $this->emailPrototype = $value;

            return $this;
        }

        if (!$this->emailPrototype instanceof \Symfony\Config\Monolog\HandlerConfig\EmailPrototypeConfig) {
            $this->_usedProperties['emailPrototype'] = true;
            $this->emailPrototype = new \Symfony\Config\Monolog\HandlerConfig\EmailPrototypeConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "emailPrototype()" has already been initialized. You cannot pass values the second time you call emailPrototype().');
        }

        return $this->emailPrototype;
    }

    /**
     * @default true
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function lazy($value): static
    {
        $this->_usedProperties['lazy'] = true;
        $this->lazy = $value;

        return $this;
    }

    /**
     * @return \Symfony\Config\Monolog\HandlerConfig\VerbosityLevelsConfig|$this
     */
    public function verbosityLevels(mixed $value = []): \Symfony\Config\Monolog\HandlerConfig\VerbosityLevelsConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['verbosityLevels'] = true;
            $this->verbosityLevels = $value;

            return $this;
        }

        if (!$this->verbosityLevels instanceof \Symfony\Config\Monolog\HandlerConfig\VerbosityLevelsConfig) {
            $this->_usedProperties['verbosityLevels'] = true;
            $this->verbosityLevels = new \Symfony\Config\Monolog\HandlerConfig\VerbosityLevelsConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "verbosityLevels()" has already been initialized. You cannot pass values the second time you call verbosityLevels().');
        }

        return $this->verbosityLevels;
    }

    /**
     * @return \Symfony\Config\Monolog\HandlerConfig\ChannelsConfig|$this
     */
    public function channels(mixed $value = []): \Symfony\Config\Monolog\HandlerConfig\ChannelsConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['channels'] = true;
            $this->channels = $value;

            return $this;
        }

        if (!$this->channels instanceof \Symfony\Config\Monolog\HandlerConfig\ChannelsConfig) {
            $this->_usedProperties['channels'] = true;
            $this->channels = new \Symfony\Config\Monolog\HandlerConfig\ChannelsConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "channels()" has already been initialized. You cannot pass values the second time you call channels().');
        }

        return $this->channels;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('type', $value)) {
            $this->_usedProperties['type'] = true;
            $this->type = $value['type'];
            unset($value['type']);
        }

        if (array_key_exists('id', $value)) {
            $this->_usedProperties['id'] = true;
            $this->id = $value['id'];
            unset($value['id']);
        }

        if (array_key_exists('priority', $value)) {
            $this->_usedProperties['priority'] = true;
            $this->priority = $value['priority'];
            unset($value['priority']);
        }

        if (array_key_exists('level', $value)) {
            $this->_usedProperties['level'] = true;
            $this->level = $value['level'];
            unset($value['level']);
        }

        if (array_key_exists('bubble', $value)) {
            $this->_usedProperties['bubble'] = true;
            $this->bubble = $value['bubble'];
            unset($value['bubble']);
        }

        if (array_key_exists('app_name', $value)) {
            $this->_usedProperties['appName'] = true;
            $this->appName = $value['app_name'];
            unset($value['app_name']);
        }

        if (array_key_exists('fill_extra_context', $value)) {
            $this->_usedProperties['fillExtraContext'] = true;
            $this->fillExtraContext = $value['fill_extra_context'];
            unset($value['fill_extra_context']);
        }

        if (array_key_exists('include_stacktraces', $value)) {
            $this->_usedProperties['includeStacktraces'] = true;
            $this->includeStacktraces = $value['include_stacktraces'];
            unset($value['include_stacktraces']);
        }

        if (array_key_exists('process_psr_3_messages', $value)) {
            $this->_usedProperties['processPsr3Messages'] = true;
            $this->processPsr3Messages = \is_array($value['process_psr_3_messages']) ? new \Symfony\Config\Monolog\HandlerConfig\ProcessPsr3MessagesConfig($value['process_psr_3_messages']) : $value['process_psr_3_messages'];
            unset($value['process_psr_3_messages']);
        }

        if (array_key_exists('path', $value)) {
            $this->_usedProperties['path'] = true;
            $this->path = $value['path'];
            unset($value['path']);
        }

        if (array_key_exists('file_permission', $value)) {
            $this->_usedProperties['filePermission'] = true;
            $this->filePermission = $value['file_permission'];
            unset($value['file_permission']);
        }

        if (array_key_exists('use_locking', $value)) {
            $this->_usedProperties['useLocking'] = true;
            $this->useLocking = $value['use_locking'];
            unset($value['use_locking']);
        }

        if (array_key_exists('filename_format', $value)) {
            $this->_usedProperties['filenameFormat'] = true;
            $this->filenameFormat = $value['filename_format'];
            unset($value['filename_format']);
        }

        if (array_key_exists('date_format', $value)) {
            $this->_usedProperties['dateFormat'] = true;
            $this->dateFormat = $value['date_format'];
            unset($value['date_format']);
        }

        if (array_key_exists('ident', $value)) {
            $this->_usedProperties['ident'] = true;
            $this->ident = $value['ident'];
            unset($value['ident']);
        }

        if (array_key_exists('logopts', $value)) {
            $this->_usedProperties['logopts'] = true;
            $this->logopts = $value['logopts'];
            unset($value['logopts']);
        }

        if (array_key_exists('facility', $value)) {
            $this->_usedProperties['facility'] = true;
            $this->facility = $value['facility'];
            unset($value['facility']);
        }

        if (array_key_exists('max_files', $value)) {
            $this->_usedProperties['maxFiles'] = true;
            $this->maxFiles = $value['max_files'];
            unset($value['max_files']);
        }

        if (array_key_exists('action_level', $value)) {
            $this->_usedProperties['actionLevel'] = true;
            $this->actionLevel = $value['action_level'];
            unset($value['action_level']);
        }

        if (array_key_exists('activation_strategy', $value)) {
            $this->_usedProperties['activationStrategy'] = true;
            $this->activationStrategy = $value['activation_strategy'];
            unset($value['activation_strategy']);
        }

        if (array_key_exists('stop_buffering', $value)) {
            $this->_usedProperties['stopBuffering'] = true;
            $this->stopBuffering = $value['stop_buffering'];
            unset($value['stop_buffering']);
        }

        if (array_key_exists('passthru_level', $value)) {
            $this->_usedProperties['passthruLevel'] = true;
            $this->passthruLevel = $value['passthru_level'];
            unset($value['passthru_level']);
        }

        if (array_key_exists('excluded_404s', $value)) {
            $this->_usedProperties['excluded404s'] = true;
            $this->excluded404s = $value['excluded_404s'];
            unset($value['excluded_404s']);
        }

        if (array_key_exists('excluded_http_codes', $value)) {
            $this->_usedProperties['excludedHttpCodes'] = true;
            $this->excludedHttpCodes = array_map(function ($v) { return \is_array($v) ? new \Symfony\Config\Monolog\HandlerConfig\ExcludedHttpCodeConfig($v) : $v; }, $value['excluded_http_codes']);
            unset($value['excluded_http_codes']);
        }

        if (array_key_exists('accepted_levels', $value)) {
            $this->_usedProperties['acceptedLevels'] = true;
            $this->acceptedLevels = $value['accepted_levels'];
            unset($value['accepted_levels']);
        }

        if (array_key_exists('min_level', $value)) {
            $this->_usedProperties['minLevel'] = true;
            $this->minLevel = $value['min_level'];
            unset($value['min_level']);
        }

        if (array_key_exists('max_level', $value)) {
            $this->_usedProperties['maxLevel'] = true;
            $this->maxLevel = $value['max_level'];
            unset($value['max_level']);
        }

        if (array_key_exists('buffer_size', $value)) {
            $this->_usedProperties['bufferSize'] = true;
            $this->bufferSize = $value['buffer_size'];
            unset($value['buffer_size']);
        }

        if (array_key_exists('flush_on_overflow', $value)) {
            $this->_usedProperties['flushOnOverflow'] = true;
            $this->flushOnOverflow = $value['flush_on_overflow'];
            unset($value['flush_on_overflow']);
        }

        if (array_key_exists('handler', $value)) {
            $this->_usedProperties['handler'] = true;
            $this->handler = $value['handler'];
            unset($value['handler']);
        }

        if (array_key_exists('url', $value)) {
            $this->_usedProperties['url'] = true;
            $this->url = $value['url'];
            unset($value['url']);
        }

        if (array_key_exists('exchange', $value)) {
            $this->_usedProperties['exchange'] = true;
            $this->exchange = $value['exchange'];
            unset($value['exchange']);
        }

        if (array_key_exists('exchange_name', $value)) {
            $this->_usedProperties['exchangeName'] = true;
            $this->exchangeName = $value['exchange_name'];
            unset($value['exchange_name']);
        }

        if (array_key_exists('room', $value)) {
            $this->_usedProperties['room'] = true;
            $this->room = $value['room'];
            unset($value['room']);
        }

        if (array_key_exists('message_format', $value)) {
            $this->_usedProperties['messageFormat'] = true;
            $this->messageFormat = $value['message_format'];
            unset($value['message_format']);
        }

        if (array_key_exists('api_version', $value)) {
            $this->_usedProperties['apiVersion'] = true;
            $this->apiVersion = $value['api_version'];
            unset($value['api_version']);
        }

        if (array_key_exists('channel', $value)) {
            $this->_usedProperties['channel'] = true;
            $this->channel = $value['channel'];
            unset($value['channel']);
        }

        if (array_key_exists('bot_name', $value)) {
            $this->_usedProperties['botName'] = true;
            $this->botName = $value['bot_name'];
            unset($value['bot_name']);
        }

        if (array_key_exists('use_attachment', $value)) {
            $this->_usedProperties['useAttachment'] = true;
            $this->useAttachment = $value['use_attachment'];
            unset($value['use_attachment']);
        }

        if (array_key_exists('use_short_attachment', $value)) {
            $this->_usedProperties['useShortAttachment'] = true;
            $this->useShortAttachment = $value['use_short_attachment'];
            unset($value['use_short_attachment']);
        }

        if (array_key_exists('include_extra', $value)) {
            $this->_usedProperties['includeExtra'] = true;
            $this->includeExtra = $value['include_extra'];
            unset($value['include_extra']);
        }

        if (array_key_exists('icon_emoji', $value)) {
            $this->_usedProperties['iconEmoji'] = true;
            $this->iconEmoji = $value['icon_emoji'];
            unset($value['icon_emoji']);
        }

        if (array_key_exists('webhook_url', $value)) {
            $this->_usedProperties['webhookUrl'] = true;
            $this->webhookUrl = $value['webhook_url'];
            unset($value['webhook_url']);
        }

        if (array_key_exists('team', $value)) {
            $this->_usedProperties['team'] = true;
            $this->team = $value['team'];
            unset($value['team']);
        }

        if (array_key_exists('notify', $value)) {
            $this->_usedProperties['notify'] = true;
            $this->notify = $value['notify'];
            unset($value['notify']);
        }

        if (array_key_exists('nickname', $value)) {
            $this->_usedProperties['nickname'] = true;
            $this->nickname = $value['nickname'];
            unset($value['nickname']);
        }

        if (array_key_exists('token', $value)) {
            $this->_usedProperties['token'] = true;
            $this->token = $value['token'];
            unset($value['token']);
        }

        if (array_key_exists('region', $value)) {
            $this->_usedProperties['region'] = true;
            $this->region = $value['region'];
            unset($value['region']);
        }

        if (array_key_exists('source', $value)) {
            $this->_usedProperties['source'] = true;
            $this->source = $value['source'];
            unset($value['source']);
        }

        if (array_key_exists('use_ssl', $value)) {
            $this->_usedProperties['useSsl'] = true;
            $this->useSsl = $value['use_ssl'];
            unset($value['use_ssl']);
        }

        if (array_key_exists('user', $value)) {
            $this->_usedProperties['user'] = true;
            $this->user = $value['user'];
            unset($value['user']);
        }

        if (array_key_exists('title', $value)) {
            $this->_usedProperties['title'] = true;
            $this->title = $value['title'];
            unset($value['title']);
        }

        if (array_key_exists('host', $value)) {
            $this->_usedProperties['host'] = true;
            $this->host = $value['host'];
            unset($value['host']);
        }

        if (array_key_exists('port', $value)) {
            $this->_usedProperties['port'] = true;
            $this->port = $value['port'];
            unset($value['port']);
        }

        if (array_key_exists('config', $value)) {
            $this->_usedProperties['config'] = true;
            $this->config = $value['config'];
            unset($value['config']);
        }

        if (array_key_exists('members', $value)) {
            $this->_usedProperties['members'] = true;
            $this->members = $value['members'];
            unset($value['members']);
        }

        if (array_key_exists('connection_string', $value)) {
            $this->_usedProperties['connectionString'] = true;
            $this->connectionString = $value['connection_string'];
            unset($value['connection_string']);
        }

        if (array_key_exists('timeout', $value)) {
            $this->_usedProperties['timeout'] = true;
            $this->timeout = $value['timeout'];
            unset($value['timeout']);
        }

        if (array_key_exists('time', $value)) {
            $this->_usedProperties['time'] = true;
            $this->time = $value['time'];
            unset($value['time']);
        }

        if (array_key_exists('deduplication_level', $value)) {
            $this->_usedProperties['deduplicationLevel'] = true;
            $this->deduplicationLevel = $value['deduplication_level'];
            unset($value['deduplication_level']);
        }

        if (array_key_exists('store', $value)) {
            $this->_usedProperties['store'] = true;
            $this->store = $value['store'];
            unset($value['store']);
        }

        if (array_key_exists('connection_timeout', $value)) {
            $this->_usedProperties['connectionTimeout'] = true;
            $this->connectionTimeout = $value['connection_timeout'];
            unset($value['connection_timeout']);
        }

        if (array_key_exists('persistent', $value)) {
            $this->_usedProperties['persistent'] = true;
            $this->persistent = $value['persistent'];
            unset($value['persistent']);
        }

        if (array_key_exists('dsn', $value)) {
            $this->_usedProperties['dsn'] = true;
            $this->dsn = $value['dsn'];
            unset($value['dsn']);
        }

        if (array_key_exists('hub_id', $value)) {
            $this->_usedProperties['hubId'] = true;
            $this->hubId = $value['hub_id'];
            unset($value['hub_id']);
        }

        if (array_key_exists('client_id', $value)) {
            $this->_usedProperties['clientId'] = true;
            $this->clientId = $value['client_id'];
            unset($value['client_id']);
        }

        if (array_key_exists('auto_log_stacks', $value)) {
            $this->_usedProperties['autoLogStacks'] = true;
            $this->autoLogStacks = $value['auto_log_stacks'];
            unset($value['auto_log_stacks']);
        }

        if (array_key_exists('release', $value)) {
            $this->_usedProperties['release'] = true;
            $this->release = $value['release'];
            unset($value['release']);
        }

        if (array_key_exists('environment', $value)) {
            $this->_usedProperties['environment'] = true;
            $this->environment = $value['environment'];
            unset($value['environment']);
        }

        if (array_key_exists('message_type', $value)) {
            $this->_usedProperties['messageType'] = true;
            $this->messageType = $value['message_type'];
            unset($value['message_type']);
        }

        if (array_key_exists('parse_mode', $value)) {
            $this->_usedProperties['parseMode'] = true;
            $this->parseMode = $value['parse_mode'];
            unset($value['parse_mode']);
        }

        if (array_key_exists('disable_webpage_preview', $value)) {
            $this->_usedProperties['disableWebpagePreview'] = true;
            $this->disableWebpagePreview = $value['disable_webpage_preview'];
            unset($value['disable_webpage_preview']);
        }

        if (array_key_exists('disable_notification', $value)) {
            $this->_usedProperties['disableNotification'] = true;
            $this->disableNotification = $value['disable_notification'];
            unset($value['disable_notification']);
        }

        if (array_key_exists('split_long_messages', $value)) {
            $this->_usedProperties['splitLongMessages'] = true;
            $this->splitLongMessages = $value['split_long_messages'];
            unset($value['split_long_messages']);
        }

        if (array_key_exists('delay_between_messages', $value)) {
            $this->_usedProperties['delayBetweenMessages'] = true;
            $this->delayBetweenMessages = $value['delay_between_messages'];
            unset($value['delay_between_messages']);
        }

        if (array_key_exists('tags', $value)) {
            $this->_usedProperties['tags'] = true;
            $this->tags = $value['tags'];
            unset($value['tags']);
        }

        if (array_key_exists('console_formater_options', $value)) {
            $this->_usedProperties['consoleFormaterOptions'] = true;
            $this->consoleFormaterOptions = $value['console_formater_options'];
            unset($value['console_formater_options']);
        }

        if (array_key_exists('console_formatter_options', $value)) {
            $this->_usedProperties['consoleFormatterOptions'] = true;
            $this->consoleFormatterOptions = $value['console_formatter_options'];
            unset($value['console_formatter_options']);
        }

        if (array_key_exists('formatter', $value)) {
            $this->_usedProperties['formatter'] = true;
            $this->formatter = $value['formatter'];
            unset($value['formatter']);
        }

        if (array_key_exists('nested', $value)) {
            $this->_usedProperties['nested'] = true;
            $this->nested = $value['nested'];
            unset($value['nested']);
        }

        if (array_key_exists('publisher', $value)) {
            $this->_usedProperties['publisher'] = true;
            $this->publisher = \is_array($value['publisher']) ? new \Symfony\Config\Monolog\HandlerConfig\PublisherConfig($value['publisher']) : $value['publisher'];
            unset($value['publisher']);
        }

        if (array_key_exists('mongo', $value)) {
            $this->_usedProperties['mongo'] = true;
            $this->mongo = \is_array($value['mongo']) ? new \Symfony\Config\Monolog\HandlerConfig\MongoConfig($value['mongo']) : $value['mongo'];
            unset($value['mongo']);
        }

        if (array_key_exists('elasticsearch', $value)) {
            $this->_usedProperties['elasticsearch'] = true;
            $this->elasticsearch = \is_array($value['elasticsearch']) ? new \Symfony\Config\Monolog\HandlerConfig\ElasticsearchConfig($value['elasticsearch']) : $value['elasticsearch'];
            unset($value['elasticsearch']);
        }

        if (array_key_exists('index', $value)) {
            $this->_usedProperties['index'] = true;
            $this->index = $value['index'];
            unset($value['index']);
        }

        if (array_key_exists('document_type', $value)) {
            $this->_usedProperties['documentType'] = true;
            $this->documentType = $value['document_type'];
            unset($value['document_type']);
        }

        if (array_key_exists('ignore_error', $value)) {
            $this->_usedProperties['ignoreError'] = true;
            $this->ignoreError = $value['ignore_error'];
            unset($value['ignore_error']);
        }

        if (array_key_exists('redis', $value)) {
            $this->_usedProperties['redis'] = true;
            $this->redis = \is_array($value['redis']) ? new \Symfony\Config\Monolog\HandlerConfig\RedisConfig($value['redis']) : $value['redis'];
            unset($value['redis']);
        }

        if (array_key_exists('predis', $value)) {
            $this->_usedProperties['predis'] = true;
            $this->predis = \is_array($value['predis']) ? new \Symfony\Config\Monolog\HandlerConfig\PredisConfig($value['predis']) : $value['predis'];
            unset($value['predis']);
        }

        if (array_key_exists('from_email', $value)) {
            $this->_usedProperties['fromEmail'] = true;
            $this->fromEmail = $value['from_email'];
            unset($value['from_email']);
        }

        if (array_key_exists('to_email', $value)) {
            $this->_usedProperties['toEmail'] = true;
            $this->toEmail = $value['to_email'];
            unset($value['to_email']);
        }

        if (array_key_exists('subject', $value)) {
            $this->_usedProperties['subject'] = true;
            $this->subject = $value['subject'];
            unset($value['subject']);
        }

        if (array_key_exists('content_type', $value)) {
            $this->_usedProperties['contentType'] = true;
            $this->contentType = $value['content_type'];
            unset($value['content_type']);
        }

        if (array_key_exists('headers', $value)) {
            $this->_usedProperties['headers'] = true;
            $this->headers = $value['headers'];
            unset($value['headers']);
        }

        if (array_key_exists('mailer', $value)) {
            $this->_usedProperties['mailer'] = true;
            $this->mailer = $value['mailer'];
            unset($value['mailer']);
        }

        if (array_key_exists('email_prototype', $value)) {
            $this->_usedProperties['emailPrototype'] = true;
            $this->emailPrototype = \is_array($value['email_prototype']) ? new \Symfony\Config\Monolog\HandlerConfig\EmailPrototypeConfig($value['email_prototype']) : $value['email_prototype'];
            unset($value['email_prototype']);
        }

        if (array_key_exists('lazy', $value)) {
            $this->_usedProperties['lazy'] = true;
            $this->lazy = $value['lazy'];
            unset($value['lazy']);
        }

        if (array_key_exists('verbosity_levels', $value)) {
            $this->_usedProperties['verbosityLevels'] = true;
            $this->verbosityLevels = \is_array($value['verbosity_levels']) ? new \Symfony\Config\Monolog\HandlerConfig\VerbosityLevelsConfig($value['verbosity_levels']) : $value['verbosity_levels'];
            unset($value['verbosity_levels']);
        }

        if (array_key_exists('channels', $value)) {
            $this->_usedProperties['channels'] = true;
            $this->channels = \is_array($value['channels']) ? new \Symfony\Config\Monolog\HandlerConfig\ChannelsConfig($value['channels']) : $value['channels'];
            unset($value['channels']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['type'])) {
            $output['type'] = $this->type;
        }
        if (isset($this->_usedProperties['id'])) {
            $output['id'] = $this->id;
        }
        if (isset($this->_usedProperties['priority'])) {
            $output['priority'] = $this->priority;
        }
        if (isset($this->_usedProperties['level'])) {
            $output['level'] = $this->level;
        }
        if (isset($this->_usedProperties['bubble'])) {
            $output['bubble'] = $this->bubble;
        }
        if (isset($this->_usedProperties['appName'])) {
            $output['app_name'] = $this->appName;
        }
        if (isset($this->_usedProperties['fillExtraContext'])) {
            $output['fill_extra_context'] = $this->fillExtraContext;
        }
        if (isset($this->_usedProperties['includeStacktraces'])) {
            $output['include_stacktraces'] = $this->includeStacktraces;
        }
        if (isset($this->_usedProperties['processPsr3Messages'])) {
            $output['process_psr_3_messages'] = $this->processPsr3Messages instanceof \Symfony\Config\Monolog\HandlerConfig\ProcessPsr3MessagesConfig ? $this->processPsr3Messages->toArray() : $this->processPsr3Messages;
        }
        if (isset($this->_usedProperties['path'])) {
            $output['path'] = $this->path;
        }
        if (isset($this->_usedProperties['filePermission'])) {
            $output['file_permission'] = $this->filePermission;
        }
        if (isset($this->_usedProperties['useLocking'])) {
            $output['use_locking'] = $this->useLocking;
        }
        if (isset($this->_usedProperties['filenameFormat'])) {
            $output['filename_format'] = $this->filenameFormat;
        }
        if (isset($this->_usedProperties['dateFormat'])) {
            $output['date_format'] = $this->dateFormat;
        }
        if (isset($this->_usedProperties['ident'])) {
            $output['ident'] = $this->ident;
        }
        if (isset($this->_usedProperties['logopts'])) {
            $output['logopts'] = $this->logopts;
        }
        if (isset($this->_usedProperties['facility'])) {
            $output['facility'] = $this->facility;
        }
        if (isset($this->_usedProperties['maxFiles'])) {
            $output['max_files'] = $this->maxFiles;
        }
        if (isset($this->_usedProperties['actionLevel'])) {
            $output['action_level'] = $this->actionLevel;
        }
        if (isset($this->_usedProperties['activationStrategy'])) {
            $output['activation_strategy'] = $this->activationStrategy;
        }
        if (isset($this->_usedProperties['stopBuffering'])) {
            $output['stop_buffering'] = $this->stopBuffering;
        }
        if (isset($this->_usedProperties['passthruLevel'])) {
            $output['passthru_level'] = $this->passthruLevel;
        }
        if (isset($this->_usedProperties['excluded404s'])) {
            $output['excluded_404s'] = $this->excluded404s;
        }
        if (isset($this->_usedProperties['excludedHttpCodes'])) {
            $output['excluded_http_codes'] = array_map(function ($v) { return $v instanceof \Symfony\Config\Monolog\HandlerConfig\ExcludedHttpCodeConfig ? $v->toArray() : $v; }, $this->excludedHttpCodes);
        }
        if (isset($this->_usedProperties['acceptedLevels'])) {
            $output['accepted_levels'] = $this->acceptedLevels;
        }
        if (isset($this->_usedProperties['minLevel'])) {
            $output['min_level'] = $this->minLevel;
        }
        if (isset($this->_usedProperties['maxLevel'])) {
            $output['max_level'] = $this->maxLevel;
        }
        if (isset($this->_usedProperties['bufferSize'])) {
            $output['buffer_size'] = $this->bufferSize;
        }
        if (isset($this->_usedProperties['flushOnOverflow'])) {
            $output['flush_on_overflow'] = $this->flushOnOverflow;
        }
        if (isset($this->_usedProperties['handler'])) {
            $output['handler'] = $this->handler;
        }
        if (isset($this->_usedProperties['url'])) {
            $output['url'] = $this->url;
        }
        if (isset($this->_usedProperties['exchange'])) {
            $output['exchange'] = $this->exchange;
        }
        if (isset($this->_usedProperties['exchangeName'])) {
            $output['exchange_name'] = $this->exchangeName;
        }
        if (isset($this->_usedProperties['room'])) {
            $output['room'] = $this->room;
        }
        if (isset($this->_usedProperties['messageFormat'])) {
            $output['message_format'] = $this->messageFormat;
        }
        if (isset($this->_usedProperties['apiVersion'])) {
            $output['api_version'] = $this->apiVersion;
        }
        if (isset($this->_usedProperties['channel'])) {
            $output['channel'] = $this->channel;
        }
        if (isset($this->_usedProperties['botName'])) {
            $output['bot_name'] = $this->botName;
        }
        if (isset($this->_usedProperties['useAttachment'])) {
            $output['use_attachment'] = $this->useAttachment;
        }
        if (isset($this->_usedProperties['useShortAttachment'])) {
            $output['use_short_attachment'] = $this->useShortAttachment;
        }
        if (isset($this->_usedProperties['includeExtra'])) {
            $output['include_extra'] = $this->includeExtra;
        }
        if (isset($this->_usedProperties['iconEmoji'])) {
            $output['icon_emoji'] = $this->iconEmoji;
        }
        if (isset($this->_usedProperties['webhookUrl'])) {
            $output['webhook_url'] = $this->webhookUrl;
        }
        if (isset($this->_usedProperties['team'])) {
            $output['team'] = $this->team;
        }
        if (isset($this->_usedProperties['notify'])) {
            $output['notify'] = $this->notify;
        }
        if (isset($this->_usedProperties['nickname'])) {
            $output['nickname'] = $this->nickname;
        }
        if (isset($this->_usedProperties['token'])) {
            $output['token'] = $this->token;
        }
        if (isset($this->_usedProperties['region'])) {
            $output['region'] = $this->region;
        }
        if (isset($this->_usedProperties['source'])) {
            $output['source'] = $this->source;
        }
        if (isset($this->_usedProperties['useSsl'])) {
            $output['use_ssl'] = $this->useSsl;
        }
        if (isset($this->_usedProperties['user'])) {
            $output['user'] = $this->user;
        }
        if (isset($this->_usedProperties['title'])) {
            $output['title'] = $this->title;
        }
        if (isset($this->_usedProperties['host'])) {
            $output['host'] = $this->host;
        }
        if (isset($this->_usedProperties['port'])) {
            $output['port'] = $this->port;
        }
        if (isset($this->_usedProperties['config'])) {
            $output['config'] = $this->config;
        }
        if (isset($this->_usedProperties['members'])) {
            $output['members'] = $this->members;
        }
        if (isset($this->_usedProperties['connectionString'])) {
            $output['connection_string'] = $this->connectionString;
        }
        if (isset($this->_usedProperties['timeout'])) {
            $output['timeout'] = $this->timeout;
        }
        if (isset($this->_usedProperties['time'])) {
            $output['time'] = $this->time;
        }
        if (isset($this->_usedProperties['deduplicationLevel'])) {
            $output['deduplication_level'] = $this->deduplicationLevel;
        }
        if (isset($this->_usedProperties['store'])) {
            $output['store'] = $this->store;
        }
        if (isset($this->_usedProperties['connectionTimeout'])) {
            $output['connection_timeout'] = $this->connectionTimeout;
        }
        if (isset($this->_usedProperties['persistent'])) {
            $output['persistent'] = $this->persistent;
        }
        if (isset($this->_usedProperties['dsn'])) {
            $output['dsn'] = $this->dsn;
        }
        if (isset($this->_usedProperties['hubId'])) {
            $output['hub_id'] = $this->hubId;
        }
        if (isset($this->_usedProperties['clientId'])) {
            $output['client_id'] = $this->clientId;
        }
        if (isset($this->_usedProperties['autoLogStacks'])) {
            $output['auto_log_stacks'] = $this->autoLogStacks;
        }
        if (isset($this->_usedProperties['release'])) {
            $output['release'] = $this->release;
        }
        if (isset($this->_usedProperties['environment'])) {
            $output['environment'] = $this->environment;
        }
        if (isset($this->_usedProperties['messageType'])) {
            $output['message_type'] = $this->messageType;
        }
        if (isset($this->_usedProperties['parseMode'])) {
            $output['parse_mode'] = $this->parseMode;
        }
        if (isset($this->_usedProperties['disableWebpagePreview'])) {
            $output['disable_webpage_preview'] = $this->disableWebpagePreview;
        }
        if (isset($this->_usedProperties['disableNotification'])) {
            $output['disable_notification'] = $this->disableNotification;
        }
        if (isset($this->_usedProperties['splitLongMessages'])) {
            $output['split_long_messages'] = $this->splitLongMessages;
        }
        if (isset($this->_usedProperties['delayBetweenMessages'])) {
            $output['delay_between_messages'] = $this->delayBetweenMessages;
        }
        if (isset($this->_usedProperties['tags'])) {
            $output['tags'] = $this->tags;
        }
        if (isset($this->_usedProperties['consoleFormaterOptions'])) {
            $output['console_formater_options'] = $this->consoleFormaterOptions;
        }
        if (isset($this->_usedProperties['consoleFormatterOptions'])) {
            $output['console_formatter_options'] = $this->consoleFormatterOptions;
        }
        if (isset($this->_usedProperties['formatter'])) {
            $output['formatter'] = $this->formatter;
        }
        if (isset($this->_usedProperties['nested'])) {
            $output['nested'] = $this->nested;
        }
        if (isset($this->_usedProperties['publisher'])) {
            $output['publisher'] = $this->publisher instanceof \Symfony\Config\Monolog\HandlerConfig\PublisherConfig ? $this->publisher->toArray() : $this->publisher;
        }
        if (isset($this->_usedProperties['mongo'])) {
            $output['mongo'] = $this->mongo instanceof \Symfony\Config\Monolog\HandlerConfig\MongoConfig ? $this->mongo->toArray() : $this->mongo;
        }
        if (isset($this->_usedProperties['elasticsearch'])) {
            $output['elasticsearch'] = $this->elasticsearch instanceof \Symfony\Config\Monolog\HandlerConfig\ElasticsearchConfig ? $this->elasticsearch->toArray() : $this->elasticsearch;
        }
        if (isset($this->_usedProperties['index'])) {
            $output['index'] = $this->index;
        }
        if (isset($this->_usedProperties['documentType'])) {
            $output['document_type'] = $this->documentType;
        }
        if (isset($this->_usedProperties['ignoreError'])) {
            $output['ignore_error'] = $this->ignoreError;
        }
        if (isset($this->_usedProperties['redis'])) {
            $output['redis'] = $this->redis instanceof \Symfony\Config\Monolog\HandlerConfig\RedisConfig ? $this->redis->toArray() : $this->redis;
        }
        if (isset($this->_usedProperties['predis'])) {
            $output['predis'] = $this->predis instanceof \Symfony\Config\Monolog\HandlerConfig\PredisConfig ? $this->predis->toArray() : $this->predis;
        }
        if (isset($this->_usedProperties['fromEmail'])) {
            $output['from_email'] = $this->fromEmail;
        }
        if (isset($this->_usedProperties['toEmail'])) {
            $output['to_email'] = $this->toEmail;
        }
        if (isset($this->_usedProperties['subject'])) {
            $output['subject'] = $this->subject;
        }
        if (isset($this->_usedProperties['contentType'])) {
            $output['content_type'] = $this->contentType;
        }
        if (isset($this->_usedProperties['headers'])) {
            $output['headers'] = $this->headers;
        }
        if (isset($this->_usedProperties['mailer'])) {
            $output['mailer'] = $this->mailer;
        }
        if (isset($this->_usedProperties['emailPrototype'])) {
            $output['email_prototype'] = $this->emailPrototype instanceof \Symfony\Config\Monolog\HandlerConfig\EmailPrototypeConfig ? $this->emailPrototype->toArray() : $this->emailPrototype;
        }
        if (isset($this->_usedProperties['lazy'])) {
            $output['lazy'] = $this->lazy;
        }
        if (isset($this->_usedProperties['verbosityLevels'])) {
            $output['verbosity_levels'] = $this->verbosityLevels instanceof \Symfony\Config\Monolog\HandlerConfig\VerbosityLevelsConfig ? $this->verbosityLevels->toArray() : $this->verbosityLevels;
        }
        if (isset($this->_usedProperties['channels'])) {
            $output['channels'] = $this->channels instanceof \Symfony\Config\Monolog\HandlerConfig\ChannelsConfig ? $this->channels->toArray() : $this->channels;
        }

        return $output;
    }

}
