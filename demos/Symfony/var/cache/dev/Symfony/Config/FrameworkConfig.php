<?php

namespace Symfony\Config;

require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'CsrfProtectionConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'FormConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'HttpCacheConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'EsiConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'SsiConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'FragmentsConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'ProfilerConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'WorkflowsConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'RouterConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'SessionConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'RequestConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'AssetsConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'TranslatorConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'ValidationConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'AnnotationsConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'SerializerConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'PropertyAccessConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'PropertyInfoConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'CacheConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'PhpErrorsConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'ExceptionsConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'WebLinkConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'LockConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'SemaphoreConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'MessengerConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'HttpClientConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'MailerConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'SecretsConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'NotifierConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'RateLimiterConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'UidConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'Framework'.\DIRECTORY_SEPARATOR.'HtmlSanitizerConfig.php';

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class FrameworkConfig implements \Symfony\Component\Config\Builder\ConfigBuilderInterface
{
    private $secret;
    private $httpMethodOverride;
    private $trustXSendfileTypeHeader;
    private $ide;
    private $test;
    private $defaultLocale;
    private $setLocaleFromAcceptLanguage;
    private $setContentLanguageFromLocale;
    private $enabledLocales;
    private $trustedHosts;
    private $trustedProxies;
    private $trustedHeaders;
    private $errorController;
    private $csrfProtection;
    private $form;
    private $httpCache;
    private $esi;
    private $ssi;
    private $fragments;
    private $profiler;
    private $workflows;
    private $router;
    private $session;
    private $request;
    private $assets;
    private $translator;
    private $validation;
    private $annotations;
    private $serializer;
    private $propertyAccess;
    private $propertyInfo;
    private $cache;
    private $phpErrors;
    private $exceptions;
    private $webLink;
    private $lock;
    private $semaphore;
    private $messenger;
    private $disallowSearchEngineIndex;
    private $httpClient;
    private $mailer;
    private $secrets;
    private $notifier;
    private $rateLimiter;
    private $uid;
    private $htmlSanitizer;
    private $_usedProperties = [];

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function secret($value): static
    {
        $this->_usedProperties['secret'] = true;
        $this->secret = $value;

        return $this;
    }

    /**
     * Set true to enable support for the '_method' request parameter to determine the intended HTTP method on POST requests. Note: When using the HttpCache, you need to call the method in your front controller instead
     * @default null
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function httpMethodOverride($value): static
    {
        $this->_usedProperties['httpMethodOverride'] = true;
        $this->httpMethodOverride = $value;

        return $this;
    }

    /**
     * Set true to enable support for xsendfile in binary file responses.
     * @default false
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function trustXSendfileTypeHeader($value): static
    {
        $this->_usedProperties['trustXSendfileTypeHeader'] = true;
        $this->trustXSendfileTypeHeader = $value;

        return $this;
    }

    /**
     * @default '%env(default::SYMFONY_IDE)%'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function ide($value): static
    {
        $this->_usedProperties['ide'] = true;
        $this->ide = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function test($value): static
    {
        $this->_usedProperties['test'] = true;
        $this->test = $value;

        return $this;
    }

    /**
     * @default 'en'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function defaultLocale($value): static
    {
        $this->_usedProperties['defaultLocale'] = true;
        $this->defaultLocale = $value;

        return $this;
    }

    /**
     * Whether to use the Accept-Language HTTP header to set the Request locale (only when the "_locale" request attribute is not passed).
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function setLocaleFromAcceptLanguage($value): static
    {
        $this->_usedProperties['setLocaleFromAcceptLanguage'] = true;
        $this->setLocaleFromAcceptLanguage = $value;

        return $this;
    }

    /**
     * Whether to set the Content-Language HTTP header on the Response using the Request locale.
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function setContentLanguageFromLocale($value): static
    {
        $this->_usedProperties['setContentLanguageFromLocale'] = true;
        $this->setContentLanguageFromLocale = $value;

        return $this;
    }

    /**
     * @param ParamConfigurator|list<ParamConfigurator|mixed> $value
     *
     * @return $this
     */
    public function enabledLocales(ParamConfigurator|array $value): static
    {
        $this->_usedProperties['enabledLocales'] = true;
        $this->enabledLocales = $value;

        return $this;
    }

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function trustedHosts(mixed $value): static
    {
        $this->_usedProperties['trustedHosts'] = true;
        $this->trustedHosts = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function trustedProxies($value): static
    {
        $this->_usedProperties['trustedProxies'] = true;
        $this->trustedProxies = $value;

        return $this;
    }

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function trustedHeaders(mixed $value): static
    {
        $this->_usedProperties['trustedHeaders'] = true;
        $this->trustedHeaders = $value;

        return $this;
    }

    /**
     * @default 'error_controller'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function errorController($value): static
    {
        $this->_usedProperties['errorController'] = true;
        $this->errorController = $value;

        return $this;
    }

    /**
     * @default {"enabled":null}
    */
    public function csrfProtection(array $value = []): \Symfony\Config\Framework\CsrfProtectionConfig
    {
        if (null === $this->csrfProtection) {
            $this->_usedProperties['csrfProtection'] = true;
            $this->csrfProtection = new \Symfony\Config\Framework\CsrfProtectionConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "csrfProtection()" has already been initialized. You cannot pass values the second time you call csrfProtection().');
        }

        return $this->csrfProtection;
    }

    /**
     * form configuration
     * @default {"enabled":true,"csrf_protection":{"enabled":null,"field_name":"_token"}}
    */
    public function form(array $value = []): \Symfony\Config\Framework\FormConfig
    {
        if (null === $this->form) {
            $this->_usedProperties['form'] = true;
            $this->form = new \Symfony\Config\Framework\FormConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "form()" has already been initialized. You cannot pass values the second time you call form().');
        }

        return $this->form;
    }

    /**
     * HTTP cache configuration
     * @default {"enabled":false,"debug":"%kernel.debug%","private_headers":[]}
     * @return \Symfony\Config\Framework\HttpCacheConfig|$this
     */
    public function httpCache(mixed $value = []): \Symfony\Config\Framework\HttpCacheConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['httpCache'] = true;
            $this->httpCache = $value;

            return $this;
        }

        if (!$this->httpCache instanceof \Symfony\Config\Framework\HttpCacheConfig) {
            $this->_usedProperties['httpCache'] = true;
            $this->httpCache = new \Symfony\Config\Framework\HttpCacheConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "httpCache()" has already been initialized. You cannot pass values the second time you call httpCache().');
        }

        return $this->httpCache;
    }

    /**
     * esi configuration
     * @default {"enabled":false}
     * @return \Symfony\Config\Framework\EsiConfig|$this
     */
    public function esi(mixed $value = []): \Symfony\Config\Framework\EsiConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['esi'] = true;
            $this->esi = $value;

            return $this;
        }

        if (!$this->esi instanceof \Symfony\Config\Framework\EsiConfig) {
            $this->_usedProperties['esi'] = true;
            $this->esi = new \Symfony\Config\Framework\EsiConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "esi()" has already been initialized. You cannot pass values the second time you call esi().');
        }

        return $this->esi;
    }

    /**
     * ssi configuration
     * @default {"enabled":false}
     * @return \Symfony\Config\Framework\SsiConfig|$this
     */
    public function ssi(mixed $value = []): \Symfony\Config\Framework\SsiConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['ssi'] = true;
            $this->ssi = $value;

            return $this;
        }

        if (!$this->ssi instanceof \Symfony\Config\Framework\SsiConfig) {
            $this->_usedProperties['ssi'] = true;
            $this->ssi = new \Symfony\Config\Framework\SsiConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "ssi()" has already been initialized. You cannot pass values the second time you call ssi().');
        }

        return $this->ssi;
    }

    /**
     * fragments configuration
     * @default {"enabled":false,"hinclude_default_template":null,"path":"\/_fragment"}
     * @return \Symfony\Config\Framework\FragmentsConfig|$this
     */
    public function fragments(mixed $value = []): \Symfony\Config\Framework\FragmentsConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['fragments'] = true;
            $this->fragments = $value;

            return $this;
        }

        if (!$this->fragments instanceof \Symfony\Config\Framework\FragmentsConfig) {
            $this->_usedProperties['fragments'] = true;
            $this->fragments = new \Symfony\Config\Framework\FragmentsConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "fragments()" has already been initialized. You cannot pass values the second time you call fragments().');
        }

        return $this->fragments;
    }

    /**
     * profiler configuration
     * @default {"enabled":false,"collect":true,"collect_parameter":null,"only_exceptions":false,"only_main_requests":false,"dsn":"file:%kernel.cache_dir%\/profiler","collect_serializer_data":false}
     * @return \Symfony\Config\Framework\ProfilerConfig|$this
     */
    public function profiler(mixed $value = []): \Symfony\Config\Framework\ProfilerConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['profiler'] = true;
            $this->profiler = $value;

            return $this;
        }

        if (!$this->profiler instanceof \Symfony\Config\Framework\ProfilerConfig) {
            $this->_usedProperties['profiler'] = true;
            $this->profiler = new \Symfony\Config\Framework\ProfilerConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "profiler()" has already been initialized. You cannot pass values the second time you call profiler().');
        }

        return $this->profiler;
    }

    /**
     * @default {"enabled":false,"workflows":[]}
     * @return \Symfony\Config\Framework\WorkflowsConfig|$this
     */
    public function workflows(mixed $value = []): \Symfony\Config\Framework\WorkflowsConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['workflows'] = true;
            $this->workflows = $value;

            return $this;
        }

        if (!$this->workflows instanceof \Symfony\Config\Framework\WorkflowsConfig) {
            $this->_usedProperties['workflows'] = true;
            $this->workflows = new \Symfony\Config\Framework\WorkflowsConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "workflows()" has already been initialized. You cannot pass values the second time you call workflows().');
        }

        return $this->workflows;
    }

    /**
     * router configuration
     * @default {"enabled":false,"default_uri":null,"http_port":80,"https_port":443,"strict_requirements":true,"utf8":true}
     * @return \Symfony\Config\Framework\RouterConfig|$this
     */
    public function router(mixed $value = []): \Symfony\Config\Framework\RouterConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['router'] = true;
            $this->router = $value;

            return $this;
        }

        if (!$this->router instanceof \Symfony\Config\Framework\RouterConfig) {
            $this->_usedProperties['router'] = true;
            $this->router = new \Symfony\Config\Framework\RouterConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "router()" has already been initialized. You cannot pass values the second time you call router().');
        }

        return $this->router;
    }

    /**
     * session configuration
     * @default {"enabled":false,"storage_factory_id":"session.storage.factory.native","handler_id":"session.handler.native_file","cookie_httponly":true,"cookie_samesite":null,"gc_probability":1,"save_path":"%kernel.cache_dir%\/sessions","metadata_update_threshold":0}
     * @return \Symfony\Config\Framework\SessionConfig|$this
     */
    public function session(mixed $value = []): \Symfony\Config\Framework\SessionConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['session'] = true;
            $this->session = $value;

            return $this;
        }

        if (!$this->session instanceof \Symfony\Config\Framework\SessionConfig) {
            $this->_usedProperties['session'] = true;
            $this->session = new \Symfony\Config\Framework\SessionConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "session()" has already been initialized. You cannot pass values the second time you call session().');
        }

        return $this->session;
    }

    /**
     * request configuration
     * @default {"enabled":false,"formats":[]}
     * @return \Symfony\Config\Framework\RequestConfig|$this
     */
    public function request(mixed $value = []): \Symfony\Config\Framework\RequestConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['request'] = true;
            $this->request = $value;

            return $this;
        }

        if (!$this->request instanceof \Symfony\Config\Framework\RequestConfig) {
            $this->_usedProperties['request'] = true;
            $this->request = new \Symfony\Config\Framework\RequestConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "request()" has already been initialized. You cannot pass values the second time you call request().');
        }

        return $this->request;
    }

    /**
     * assets configuration
     * @default {"enabled":true,"strict_mode":false,"version_strategy":null,"version":null,"version_format":"%%s?%%s","json_manifest_path":null,"base_path":"","base_urls":[],"packages":[]}
    */
    public function assets(array $value = []): \Symfony\Config\Framework\AssetsConfig
    {
        if (null === $this->assets) {
            $this->_usedProperties['assets'] = true;
            $this->assets = new \Symfony\Config\Framework\AssetsConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "assets()" has already been initialized. You cannot pass values the second time you call assets().');
        }

        return $this->assets;
    }

    /**
     * translator configuration
     * @default {"enabled":true,"fallbacks":[],"logging":false,"formatter":"translator.formatter.default","cache_dir":"%kernel.cache_dir%\/translations","default_path":"%kernel.project_dir%\/translations","paths":[],"pseudo_localization":{"enabled":false,"accents":true,"expansion_factor":1,"brackets":true,"parse_html":false,"localizable_html_attributes":[]},"providers":[]}
    */
    public function translator(array $value = []): \Symfony\Config\Framework\TranslatorConfig
    {
        if (null === $this->translator) {
            $this->_usedProperties['translator'] = true;
            $this->translator = new \Symfony\Config\Framework\TranslatorConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "translator()" has already been initialized. You cannot pass values the second time you call translator().');
        }

        return $this->translator;
    }

    /**
     * validation configuration
     * @default {"enabled":true,"enable_annotations":true,"static_method":["loadValidatorMetadata"],"translation_domain":"validators","mapping":{"paths":[]},"not_compromised_password":{"enabled":true,"endpoint":null},"auto_mapping":[]}
    */
    public function validation(array $value = []): \Symfony\Config\Framework\ValidationConfig
    {
        if (null === $this->validation) {
            $this->_usedProperties['validation'] = true;
            $this->validation = new \Symfony\Config\Framework\ValidationConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "validation()" has already been initialized. You cannot pass values the second time you call validation().');
        }

        return $this->validation;
    }

    /**
     * annotation configuration
     * @default {"enabled":true,"cache":"php_array","file_cache_dir":"%kernel.cache_dir%\/annotations","debug":true}
    */
    public function annotations(array $value = []): \Symfony\Config\Framework\AnnotationsConfig
    {
        if (null === $this->annotations) {
            $this->_usedProperties['annotations'] = true;
            $this->annotations = new \Symfony\Config\Framework\AnnotationsConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "annotations()" has already been initialized. You cannot pass values the second time you call annotations().');
        }

        return $this->annotations;
    }

    /**
     * serializer configuration
     * @default {"enabled":false,"enable_annotations":true,"mapping":{"paths":[]},"default_context":[]}
     * @return \Symfony\Config\Framework\SerializerConfig|$this
     */
    public function serializer(mixed $value = []): \Symfony\Config\Framework\SerializerConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['serializer'] = true;
            $this->serializer = $value;

            return $this;
        }

        if (!$this->serializer instanceof \Symfony\Config\Framework\SerializerConfig) {
            $this->_usedProperties['serializer'] = true;
            $this->serializer = new \Symfony\Config\Framework\SerializerConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "serializer()" has already been initialized. You cannot pass values the second time you call serializer().');
        }

        return $this->serializer;
    }

    /**
     * Property access configuration
     * @default {"enabled":true,"magic_call":false,"magic_get":true,"magic_set":true,"throw_exception_on_invalid_index":false,"throw_exception_on_invalid_property_path":true}
    */
    public function propertyAccess(array $value = []): \Symfony\Config\Framework\PropertyAccessConfig
    {
        if (null === $this->propertyAccess) {
            $this->_usedProperties['propertyAccess'] = true;
            $this->propertyAccess = new \Symfony\Config\Framework\PropertyAccessConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "propertyAccess()" has already been initialized. You cannot pass values the second time you call propertyAccess().');
        }

        return $this->propertyAccess;
    }

    /**
     * Property info configuration
     * @default {"enabled":true}
    */
    public function propertyInfo(array $value = []): \Symfony\Config\Framework\PropertyInfoConfig
    {
        if (null === $this->propertyInfo) {
            $this->_usedProperties['propertyInfo'] = true;
            $this->propertyInfo = new \Symfony\Config\Framework\PropertyInfoConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "propertyInfo()" has already been initialized. You cannot pass values the second time you call propertyInfo().');
        }

        return $this->propertyInfo;
    }

    /**
     * Cache configuration
     * @default {"prefix_seed":"_%kernel.project_dir%.%kernel.container_class%","app":"cache.adapter.filesystem","system":"cache.adapter.system","directory":"%kernel.cache_dir%\/pools\/app","default_redis_provider":"redis:\/\/localhost","default_memcached_provider":"memcached:\/\/localhost","default_doctrine_dbal_provider":"database_connection","default_pdo_provider":null,"pools":[]}
    */
    public function cache(array $value = []): \Symfony\Config\Framework\CacheConfig
    {
        if (null === $this->cache) {
            $this->_usedProperties['cache'] = true;
            $this->cache = new \Symfony\Config\Framework\CacheConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "cache()" has already been initialized. You cannot pass values the second time you call cache().');
        }

        return $this->cache;
    }

    /**
     * PHP errors handling configuration
     * @default {"log":true,"throw":true}
    */
    public function phpErrors(array $value = []): \Symfony\Config\Framework\PhpErrorsConfig
    {
        if (null === $this->phpErrors) {
            $this->_usedProperties['phpErrors'] = true;
            $this->phpErrors = new \Symfony\Config\Framework\PhpErrorsConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "phpErrors()" has already been initialized. You cannot pass values the second time you call phpErrors().');
        }

        return $this->phpErrors;
    }

    /**
     * Exception handling configuration
     * @return \Symfony\Config\Framework\ExceptionsConfig|$this
     */
    public function exceptions(mixed $value = []): \Symfony\Config\Framework\ExceptionsConfig|static
    {
        $this->_usedProperties['exceptions'] = true;
        if (!\is_array($value)) {
            $this->exceptions[] = $value;

            return $this;
        }

        return $this->exceptions[] = new \Symfony\Config\Framework\ExceptionsConfig($value);
    }

    /**
     * web links configuration
     * @default {"enabled":false}
     * @return \Symfony\Config\Framework\WebLinkConfig|$this
     */
    public function webLink(mixed $value = []): \Symfony\Config\Framework\WebLinkConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['webLink'] = true;
            $this->webLink = $value;

            return $this;
        }

        if (!$this->webLink instanceof \Symfony\Config\Framework\WebLinkConfig) {
            $this->_usedProperties['webLink'] = true;
            $this->webLink = new \Symfony\Config\Framework\WebLinkConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "webLink()" has already been initialized. You cannot pass values the second time you call webLink().');
        }

        return $this->webLink;
    }

    /**
     * Lock configuration
     * @default {"enabled":false,"resources":{"default":["flock"]}}
     * @return \Symfony\Config\Framework\LockConfig|$this
     */
    public function lock(mixed $value = []): \Symfony\Config\Framework\LockConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['lock'] = true;
            $this->lock = $value;

            return $this;
        }

        if (!$this->lock instanceof \Symfony\Config\Framework\LockConfig) {
            $this->_usedProperties['lock'] = true;
            $this->lock = new \Symfony\Config\Framework\LockConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "lock()" has already been initialized. You cannot pass values the second time you call lock().');
        }

        return $this->lock;
    }

    /**
     * Semaphore configuration
     * @default {"enabled":false,"resources":[]}
     * @return \Symfony\Config\Framework\SemaphoreConfig|$this
     */
    public function semaphore(mixed $value = []): \Symfony\Config\Framework\SemaphoreConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['semaphore'] = true;
            $this->semaphore = $value;

            return $this;
        }

        if (!$this->semaphore instanceof \Symfony\Config\Framework\SemaphoreConfig) {
            $this->_usedProperties['semaphore'] = true;
            $this->semaphore = new \Symfony\Config\Framework\SemaphoreConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "semaphore()" has already been initialized. You cannot pass values the second time you call semaphore().');
        }

        return $this->semaphore;
    }

    /**
     * Messenger configuration
     * @default {"enabled":false,"routing":[],"serializer":{"default_serializer":"messenger.transport.native_php_serializer","symfony_serializer":{"format":"json","context":[]}},"transports":[],"failure_transport":null,"reset_on_message":true,"default_bus":null,"buses":{"messenger.bus.default":{"default_middleware":true,"middleware":[]}}}
     * @return \Symfony\Config\Framework\MessengerConfig|$this
     */
    public function messenger(mixed $value = []): \Symfony\Config\Framework\MessengerConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['messenger'] = true;
            $this->messenger = $value;

            return $this;
        }

        if (!$this->messenger instanceof \Symfony\Config\Framework\MessengerConfig) {
            $this->_usedProperties['messenger'] = true;
            $this->messenger = new \Symfony\Config\Framework\MessengerConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "messenger()" has already been initialized. You cannot pass values the second time you call messenger().');
        }

        return $this->messenger;
    }

    /**
     * Enabled by default when debug is enabled.
     * @default true
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function disallowSearchEngineIndex($value): static
    {
        $this->_usedProperties['disallowSearchEngineIndex'] = true;
        $this->disallowSearchEngineIndex = $value;

        return $this;
    }

    /**
     * HTTP Client configuration
     * @default {"enabled":true,"scoped_clients":[]}
     * @return \Symfony\Config\Framework\HttpClientConfig|$this
     */
    public function httpClient(mixed $value = []): \Symfony\Config\Framework\HttpClientConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['httpClient'] = true;
            $this->httpClient = $value;

            return $this;
        }

        if (!$this->httpClient instanceof \Symfony\Config\Framework\HttpClientConfig) {
            $this->_usedProperties['httpClient'] = true;
            $this->httpClient = new \Symfony\Config\Framework\HttpClientConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "httpClient()" has already been initialized. You cannot pass values the second time you call httpClient().');
        }

        return $this->httpClient;
    }

    /**
     * Mailer configuration
     * @default {"enabled":true,"message_bus":null,"dsn":null,"transports":[],"headers":[]}
    */
    public function mailer(array $value = []): \Symfony\Config\Framework\MailerConfig
    {
        if (null === $this->mailer) {
            $this->_usedProperties['mailer'] = true;
            $this->mailer = new \Symfony\Config\Framework\MailerConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "mailer()" has already been initialized. You cannot pass values the second time you call mailer().');
        }

        return $this->mailer;
    }

    /**
     * @default {"enabled":true,"vault_directory":"%kernel.project_dir%\/config\/secrets\/%kernel.runtime_environment%","local_dotenv_file":"%kernel.project_dir%\/.env.%kernel.environment%.local","decryption_env_var":"base64:default::SYMFONY_DECRYPTION_SECRET"}
    */
    public function secrets(array $value = []): \Symfony\Config\Framework\SecretsConfig
    {
        if (null === $this->secrets) {
            $this->_usedProperties['secrets'] = true;
            $this->secrets = new \Symfony\Config\Framework\SecretsConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "secrets()" has already been initialized. You cannot pass values the second time you call secrets().');
        }

        return $this->secrets;
    }

    /**
     * Notifier configuration
     * @default {"enabled":false,"chatter_transports":[],"texter_transports":[],"notification_on_failed_messages":false,"channel_policy":[],"admin_recipients":[]}
     * @return \Symfony\Config\Framework\NotifierConfig|$this
     */
    public function notifier(mixed $value = []): \Symfony\Config\Framework\NotifierConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['notifier'] = true;
            $this->notifier = $value;

            return $this;
        }

        if (!$this->notifier instanceof \Symfony\Config\Framework\NotifierConfig) {
            $this->_usedProperties['notifier'] = true;
            $this->notifier = new \Symfony\Config\Framework\NotifierConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "notifier()" has already been initialized. You cannot pass values the second time you call notifier().');
        }

        return $this->notifier;
    }

    /**
     * Rate limiter configuration
     * @default {"enabled":false,"limiters":[]}
     * @return \Symfony\Config\Framework\RateLimiterConfig|$this
     */
    public function rateLimiter(mixed $value = []): \Symfony\Config\Framework\RateLimiterConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['rateLimiter'] = true;
            $this->rateLimiter = $value;

            return $this;
        }

        if (!$this->rateLimiter instanceof \Symfony\Config\Framework\RateLimiterConfig) {
            $this->_usedProperties['rateLimiter'] = true;
            $this->rateLimiter = new \Symfony\Config\Framework\RateLimiterConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "rateLimiter()" has already been initialized. You cannot pass values the second time you call rateLimiter().');
        }

        return $this->rateLimiter;
    }

    /**
     * Uid configuration
     * @default {"enabled":false,"default_uuid_version":6,"name_based_uuid_version":5,"time_based_uuid_version":6}
     * @return \Symfony\Config\Framework\UidConfig|$this
     */
    public function uid(mixed $value = []): \Symfony\Config\Framework\UidConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['uid'] = true;
            $this->uid = $value;

            return $this;
        }

        if (!$this->uid instanceof \Symfony\Config\Framework\UidConfig) {
            $this->_usedProperties['uid'] = true;
            $this->uid = new \Symfony\Config\Framework\UidConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "uid()" has already been initialized. You cannot pass values the second time you call uid().');
        }

        return $this->uid;
    }

    /**
     * HtmlSanitizer configuration
     * @default {"enabled":true,"sanitizers":[]}
    */
    public function htmlSanitizer(array $value = []): \Symfony\Config\Framework\HtmlSanitizerConfig
    {
        if (null === $this->htmlSanitizer) {
            $this->_usedProperties['htmlSanitizer'] = true;
            $this->htmlSanitizer = new \Symfony\Config\Framework\HtmlSanitizerConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "htmlSanitizer()" has already been initialized. You cannot pass values the second time you call htmlSanitizer().');
        }

        return $this->htmlSanitizer;
    }

    public function getExtensionAlias(): string
    {
        return 'framework';
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('secret', $value)) {
            $this->_usedProperties['secret'] = true;
            $this->secret = $value['secret'];
            unset($value['secret']);
        }

        if (array_key_exists('http_method_override', $value)) {
            $this->_usedProperties['httpMethodOverride'] = true;
            $this->httpMethodOverride = $value['http_method_override'];
            unset($value['http_method_override']);
        }

        if (array_key_exists('trust_x_sendfile_type_header', $value)) {
            $this->_usedProperties['trustXSendfileTypeHeader'] = true;
            $this->trustXSendfileTypeHeader = $value['trust_x_sendfile_type_header'];
            unset($value['trust_x_sendfile_type_header']);
        }

        if (array_key_exists('ide', $value)) {
            $this->_usedProperties['ide'] = true;
            $this->ide = $value['ide'];
            unset($value['ide']);
        }

        if (array_key_exists('test', $value)) {
            $this->_usedProperties['test'] = true;
            $this->test = $value['test'];
            unset($value['test']);
        }

        if (array_key_exists('default_locale', $value)) {
            $this->_usedProperties['defaultLocale'] = true;
            $this->defaultLocale = $value['default_locale'];
            unset($value['default_locale']);
        }

        if (array_key_exists('set_locale_from_accept_language', $value)) {
            $this->_usedProperties['setLocaleFromAcceptLanguage'] = true;
            $this->setLocaleFromAcceptLanguage = $value['set_locale_from_accept_language'];
            unset($value['set_locale_from_accept_language']);
        }

        if (array_key_exists('set_content_language_from_locale', $value)) {
            $this->_usedProperties['setContentLanguageFromLocale'] = true;
            $this->setContentLanguageFromLocale = $value['set_content_language_from_locale'];
            unset($value['set_content_language_from_locale']);
        }

        if (array_key_exists('enabled_locales', $value)) {
            $this->_usedProperties['enabledLocales'] = true;
            $this->enabledLocales = $value['enabled_locales'];
            unset($value['enabled_locales']);
        }

        if (array_key_exists('trusted_hosts', $value)) {
            $this->_usedProperties['trustedHosts'] = true;
            $this->trustedHosts = $value['trusted_hosts'];
            unset($value['trusted_hosts']);
        }

        if (array_key_exists('trusted_proxies', $value)) {
            $this->_usedProperties['trustedProxies'] = true;
            $this->trustedProxies = $value['trusted_proxies'];
            unset($value['trusted_proxies']);
        }

        if (array_key_exists('trusted_headers', $value)) {
            $this->_usedProperties['trustedHeaders'] = true;
            $this->trustedHeaders = $value['trusted_headers'];
            unset($value['trusted_headers']);
        }

        if (array_key_exists('error_controller', $value)) {
            $this->_usedProperties['errorController'] = true;
            $this->errorController = $value['error_controller'];
            unset($value['error_controller']);
        }

        if (array_key_exists('csrf_protection', $value)) {
            $this->_usedProperties['csrfProtection'] = true;
            $this->csrfProtection = new \Symfony\Config\Framework\CsrfProtectionConfig($value['csrf_protection']);
            unset($value['csrf_protection']);
        }

        if (array_key_exists('form', $value)) {
            $this->_usedProperties['form'] = true;
            $this->form = new \Symfony\Config\Framework\FormConfig($value['form']);
            unset($value['form']);
        }

        if (array_key_exists('http_cache', $value)) {
            $this->_usedProperties['httpCache'] = true;
            $this->httpCache = \is_array($value['http_cache']) ? new \Symfony\Config\Framework\HttpCacheConfig($value['http_cache']) : $value['http_cache'];
            unset($value['http_cache']);
        }

        if (array_key_exists('esi', $value)) {
            $this->_usedProperties['esi'] = true;
            $this->esi = \is_array($value['esi']) ? new \Symfony\Config\Framework\EsiConfig($value['esi']) : $value['esi'];
            unset($value['esi']);
        }

        if (array_key_exists('ssi', $value)) {
            $this->_usedProperties['ssi'] = true;
            $this->ssi = \is_array($value['ssi']) ? new \Symfony\Config\Framework\SsiConfig($value['ssi']) : $value['ssi'];
            unset($value['ssi']);
        }

        if (array_key_exists('fragments', $value)) {
            $this->_usedProperties['fragments'] = true;
            $this->fragments = \is_array($value['fragments']) ? new \Symfony\Config\Framework\FragmentsConfig($value['fragments']) : $value['fragments'];
            unset($value['fragments']);
        }

        if (array_key_exists('profiler', $value)) {
            $this->_usedProperties['profiler'] = true;
            $this->profiler = \is_array($value['profiler']) ? new \Symfony\Config\Framework\ProfilerConfig($value['profiler']) : $value['profiler'];
            unset($value['profiler']);
        }

        if (array_key_exists('workflows', $value)) {
            $this->_usedProperties['workflows'] = true;
            $this->workflows = \is_array($value['workflows']) ? new \Symfony\Config\Framework\WorkflowsConfig($value['workflows']) : $value['workflows'];
            unset($value['workflows']);
        }

        if (array_key_exists('router', $value)) {
            $this->_usedProperties['router'] = true;
            $this->router = \is_array($value['router']) ? new \Symfony\Config\Framework\RouterConfig($value['router']) : $value['router'];
            unset($value['router']);
        }

        if (array_key_exists('session', $value)) {
            $this->_usedProperties['session'] = true;
            $this->session = \is_array($value['session']) ? new \Symfony\Config\Framework\SessionConfig($value['session']) : $value['session'];
            unset($value['session']);
        }

        if (array_key_exists('request', $value)) {
            $this->_usedProperties['request'] = true;
            $this->request = \is_array($value['request']) ? new \Symfony\Config\Framework\RequestConfig($value['request']) : $value['request'];
            unset($value['request']);
        }

        if (array_key_exists('assets', $value)) {
            $this->_usedProperties['assets'] = true;
            $this->assets = new \Symfony\Config\Framework\AssetsConfig($value['assets']);
            unset($value['assets']);
        }

        if (array_key_exists('translator', $value)) {
            $this->_usedProperties['translator'] = true;
            $this->translator = new \Symfony\Config\Framework\TranslatorConfig($value['translator']);
            unset($value['translator']);
        }

        if (array_key_exists('validation', $value)) {
            $this->_usedProperties['validation'] = true;
            $this->validation = new \Symfony\Config\Framework\ValidationConfig($value['validation']);
            unset($value['validation']);
        }

        if (array_key_exists('annotations', $value)) {
            $this->_usedProperties['annotations'] = true;
            $this->annotations = new \Symfony\Config\Framework\AnnotationsConfig($value['annotations']);
            unset($value['annotations']);
        }

        if (array_key_exists('serializer', $value)) {
            $this->_usedProperties['serializer'] = true;
            $this->serializer = \is_array($value['serializer']) ? new \Symfony\Config\Framework\SerializerConfig($value['serializer']) : $value['serializer'];
            unset($value['serializer']);
        }

        if (array_key_exists('property_access', $value)) {
            $this->_usedProperties['propertyAccess'] = true;
            $this->propertyAccess = new \Symfony\Config\Framework\PropertyAccessConfig($value['property_access']);
            unset($value['property_access']);
        }

        if (array_key_exists('property_info', $value)) {
            $this->_usedProperties['propertyInfo'] = true;
            $this->propertyInfo = new \Symfony\Config\Framework\PropertyInfoConfig($value['property_info']);
            unset($value['property_info']);
        }

        if (array_key_exists('cache', $value)) {
            $this->_usedProperties['cache'] = true;
            $this->cache = new \Symfony\Config\Framework\CacheConfig($value['cache']);
            unset($value['cache']);
        }

        if (array_key_exists('php_errors', $value)) {
            $this->_usedProperties['phpErrors'] = true;
            $this->phpErrors = new \Symfony\Config\Framework\PhpErrorsConfig($value['php_errors']);
            unset($value['php_errors']);
        }

        if (array_key_exists('exceptions', $value)) {
            $this->_usedProperties['exceptions'] = true;
            $this->exceptions = array_map(function ($v) { return \is_array($v) ? new \Symfony\Config\Framework\ExceptionsConfig($v) : $v; }, $value['exceptions']);
            unset($value['exceptions']);
        }

        if (array_key_exists('web_link', $value)) {
            $this->_usedProperties['webLink'] = true;
            $this->webLink = \is_array($value['web_link']) ? new \Symfony\Config\Framework\WebLinkConfig($value['web_link']) : $value['web_link'];
            unset($value['web_link']);
        }

        if (array_key_exists('lock', $value)) {
            $this->_usedProperties['lock'] = true;
            $this->lock = \is_array($value['lock']) ? new \Symfony\Config\Framework\LockConfig($value['lock']) : $value['lock'];
            unset($value['lock']);
        }

        if (array_key_exists('semaphore', $value)) {
            $this->_usedProperties['semaphore'] = true;
            $this->semaphore = \is_array($value['semaphore']) ? new \Symfony\Config\Framework\SemaphoreConfig($value['semaphore']) : $value['semaphore'];
            unset($value['semaphore']);
        }

        if (array_key_exists('messenger', $value)) {
            $this->_usedProperties['messenger'] = true;
            $this->messenger = \is_array($value['messenger']) ? new \Symfony\Config\Framework\MessengerConfig($value['messenger']) : $value['messenger'];
            unset($value['messenger']);
        }

        if (array_key_exists('disallow_search_engine_index', $value)) {
            $this->_usedProperties['disallowSearchEngineIndex'] = true;
            $this->disallowSearchEngineIndex = $value['disallow_search_engine_index'];
            unset($value['disallow_search_engine_index']);
        }

        if (array_key_exists('http_client', $value)) {
            $this->_usedProperties['httpClient'] = true;
            $this->httpClient = \is_array($value['http_client']) ? new \Symfony\Config\Framework\HttpClientConfig($value['http_client']) : $value['http_client'];
            unset($value['http_client']);
        }

        if (array_key_exists('mailer', $value)) {
            $this->_usedProperties['mailer'] = true;
            $this->mailer = new \Symfony\Config\Framework\MailerConfig($value['mailer']);
            unset($value['mailer']);
        }

        if (array_key_exists('secrets', $value)) {
            $this->_usedProperties['secrets'] = true;
            $this->secrets = new \Symfony\Config\Framework\SecretsConfig($value['secrets']);
            unset($value['secrets']);
        }

        if (array_key_exists('notifier', $value)) {
            $this->_usedProperties['notifier'] = true;
            $this->notifier = \is_array($value['notifier']) ? new \Symfony\Config\Framework\NotifierConfig($value['notifier']) : $value['notifier'];
            unset($value['notifier']);
        }

        if (array_key_exists('rate_limiter', $value)) {
            $this->_usedProperties['rateLimiter'] = true;
            $this->rateLimiter = \is_array($value['rate_limiter']) ? new \Symfony\Config\Framework\RateLimiterConfig($value['rate_limiter']) : $value['rate_limiter'];
            unset($value['rate_limiter']);
        }

        if (array_key_exists('uid', $value)) {
            $this->_usedProperties['uid'] = true;
            $this->uid = \is_array($value['uid']) ? new \Symfony\Config\Framework\UidConfig($value['uid']) : $value['uid'];
            unset($value['uid']);
        }

        if (array_key_exists('html_sanitizer', $value)) {
            $this->_usedProperties['htmlSanitizer'] = true;
            $this->htmlSanitizer = new \Symfony\Config\Framework\HtmlSanitizerConfig($value['html_sanitizer']);
            unset($value['html_sanitizer']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['secret'])) {
            $output['secret'] = $this->secret;
        }
        if (isset($this->_usedProperties['httpMethodOverride'])) {
            $output['http_method_override'] = $this->httpMethodOverride;
        }
        if (isset($this->_usedProperties['trustXSendfileTypeHeader'])) {
            $output['trust_x_sendfile_type_header'] = $this->trustXSendfileTypeHeader;
        }
        if (isset($this->_usedProperties['ide'])) {
            $output['ide'] = $this->ide;
        }
        if (isset($this->_usedProperties['test'])) {
            $output['test'] = $this->test;
        }
        if (isset($this->_usedProperties['defaultLocale'])) {
            $output['default_locale'] = $this->defaultLocale;
        }
        if (isset($this->_usedProperties['setLocaleFromAcceptLanguage'])) {
            $output['set_locale_from_accept_language'] = $this->setLocaleFromAcceptLanguage;
        }
        if (isset($this->_usedProperties['setContentLanguageFromLocale'])) {
            $output['set_content_language_from_locale'] = $this->setContentLanguageFromLocale;
        }
        if (isset($this->_usedProperties['enabledLocales'])) {
            $output['enabled_locales'] = $this->enabledLocales;
        }
        if (isset($this->_usedProperties['trustedHosts'])) {
            $output['trusted_hosts'] = $this->trustedHosts;
        }
        if (isset($this->_usedProperties['trustedProxies'])) {
            $output['trusted_proxies'] = $this->trustedProxies;
        }
        if (isset($this->_usedProperties['trustedHeaders'])) {
            $output['trusted_headers'] = $this->trustedHeaders;
        }
        if (isset($this->_usedProperties['errorController'])) {
            $output['error_controller'] = $this->errorController;
        }
        if (isset($this->_usedProperties['csrfProtection'])) {
            $output['csrf_protection'] = $this->csrfProtection->toArray();
        }
        if (isset($this->_usedProperties['form'])) {
            $output['form'] = $this->form->toArray();
        }
        if (isset($this->_usedProperties['httpCache'])) {
            $output['http_cache'] = $this->httpCache instanceof \Symfony\Config\Framework\HttpCacheConfig ? $this->httpCache->toArray() : $this->httpCache;
        }
        if (isset($this->_usedProperties['esi'])) {
            $output['esi'] = $this->esi instanceof \Symfony\Config\Framework\EsiConfig ? $this->esi->toArray() : $this->esi;
        }
        if (isset($this->_usedProperties['ssi'])) {
            $output['ssi'] = $this->ssi instanceof \Symfony\Config\Framework\SsiConfig ? $this->ssi->toArray() : $this->ssi;
        }
        if (isset($this->_usedProperties['fragments'])) {
            $output['fragments'] = $this->fragments instanceof \Symfony\Config\Framework\FragmentsConfig ? $this->fragments->toArray() : $this->fragments;
        }
        if (isset($this->_usedProperties['profiler'])) {
            $output['profiler'] = $this->profiler instanceof \Symfony\Config\Framework\ProfilerConfig ? $this->profiler->toArray() : $this->profiler;
        }
        if (isset($this->_usedProperties['workflows'])) {
            $output['workflows'] = $this->workflows instanceof \Symfony\Config\Framework\WorkflowsConfig ? $this->workflows->toArray() : $this->workflows;
        }
        if (isset($this->_usedProperties['router'])) {
            $output['router'] = $this->router instanceof \Symfony\Config\Framework\RouterConfig ? $this->router->toArray() : $this->router;
        }
        if (isset($this->_usedProperties['session'])) {
            $output['session'] = $this->session instanceof \Symfony\Config\Framework\SessionConfig ? $this->session->toArray() : $this->session;
        }
        if (isset($this->_usedProperties['request'])) {
            $output['request'] = $this->request instanceof \Symfony\Config\Framework\RequestConfig ? $this->request->toArray() : $this->request;
        }
        if (isset($this->_usedProperties['assets'])) {
            $output['assets'] = $this->assets->toArray();
        }
        if (isset($this->_usedProperties['translator'])) {
            $output['translator'] = $this->translator->toArray();
        }
        if (isset($this->_usedProperties['validation'])) {
            $output['validation'] = $this->validation->toArray();
        }
        if (isset($this->_usedProperties['annotations'])) {
            $output['annotations'] = $this->annotations->toArray();
        }
        if (isset($this->_usedProperties['serializer'])) {
            $output['serializer'] = $this->serializer instanceof \Symfony\Config\Framework\SerializerConfig ? $this->serializer->toArray() : $this->serializer;
        }
        if (isset($this->_usedProperties['propertyAccess'])) {
            $output['property_access'] = $this->propertyAccess->toArray();
        }
        if (isset($this->_usedProperties['propertyInfo'])) {
            $output['property_info'] = $this->propertyInfo->toArray();
        }
        if (isset($this->_usedProperties['cache'])) {
            $output['cache'] = $this->cache->toArray();
        }
        if (isset($this->_usedProperties['phpErrors'])) {
            $output['php_errors'] = $this->phpErrors->toArray();
        }
        if (isset($this->_usedProperties['exceptions'])) {
            $output['exceptions'] = array_map(function ($v) { return $v instanceof \Symfony\Config\Framework\ExceptionsConfig ? $v->toArray() : $v; }, $this->exceptions);
        }
        if (isset($this->_usedProperties['webLink'])) {
            $output['web_link'] = $this->webLink instanceof \Symfony\Config\Framework\WebLinkConfig ? $this->webLink->toArray() : $this->webLink;
        }
        if (isset($this->_usedProperties['lock'])) {
            $output['lock'] = $this->lock instanceof \Symfony\Config\Framework\LockConfig ? $this->lock->toArray() : $this->lock;
        }
        if (isset($this->_usedProperties['semaphore'])) {
            $output['semaphore'] = $this->semaphore instanceof \Symfony\Config\Framework\SemaphoreConfig ? $this->semaphore->toArray() : $this->semaphore;
        }
        if (isset($this->_usedProperties['messenger'])) {
            $output['messenger'] = $this->messenger instanceof \Symfony\Config\Framework\MessengerConfig ? $this->messenger->toArray() : $this->messenger;
        }
        if (isset($this->_usedProperties['disallowSearchEngineIndex'])) {
            $output['disallow_search_engine_index'] = $this->disallowSearchEngineIndex;
        }
        if (isset($this->_usedProperties['httpClient'])) {
            $output['http_client'] = $this->httpClient instanceof \Symfony\Config\Framework\HttpClientConfig ? $this->httpClient->toArray() : $this->httpClient;
        }
        if (isset($this->_usedProperties['mailer'])) {
            $output['mailer'] = $this->mailer->toArray();
        }
        if (isset($this->_usedProperties['secrets'])) {
            $output['secrets'] = $this->secrets->toArray();
        }
        if (isset($this->_usedProperties['notifier'])) {
            $output['notifier'] = $this->notifier instanceof \Symfony\Config\Framework\NotifierConfig ? $this->notifier->toArray() : $this->notifier;
        }
        if (isset($this->_usedProperties['rateLimiter'])) {
            $output['rate_limiter'] = $this->rateLimiter instanceof \Symfony\Config\Framework\RateLimiterConfig ? $this->rateLimiter->toArray() : $this->rateLimiter;
        }
        if (isset($this->_usedProperties['uid'])) {
            $output['uid'] = $this->uid instanceof \Symfony\Config\Framework\UidConfig ? $this->uid->toArray() : $this->uid;
        }
        if (isset($this->_usedProperties['htmlSanitizer'])) {
            $output['html_sanitizer'] = $this->htmlSanitizer->toArray();
        }

        return $output;
    }

}
