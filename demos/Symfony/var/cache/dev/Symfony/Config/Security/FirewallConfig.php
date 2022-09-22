<?php

namespace Symfony\Config\Security;

require_once __DIR__.\DIRECTORY_SEPARATOR.'FirewallConfig'.\DIRECTORY_SEPARATOR.'LogoutConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'FirewallConfig'.\DIRECTORY_SEPARATOR.'SwitchUserConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'FirewallConfig'.\DIRECTORY_SEPARATOR.'LoginThrottlingConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'FirewallConfig'.\DIRECTORY_SEPARATOR.'X509Config.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'FirewallConfig'.\DIRECTORY_SEPARATOR.'RemoteUserConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'FirewallConfig'.\DIRECTORY_SEPARATOR.'LoginLinkConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'FirewallConfig'.\DIRECTORY_SEPARATOR.'FormLoginConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'FirewallConfig'.\DIRECTORY_SEPARATOR.'FormLoginLdapConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'FirewallConfig'.\DIRECTORY_SEPARATOR.'JsonLoginConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'FirewallConfig'.\DIRECTORY_SEPARATOR.'JsonLoginLdapConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'FirewallConfig'.\DIRECTORY_SEPARATOR.'HttpBasicConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'FirewallConfig'.\DIRECTORY_SEPARATOR.'HttpBasicLdapConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'FirewallConfig'.\DIRECTORY_SEPARATOR.'RememberMeConfig.php';

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class FirewallConfig 
{
    private $pattern;
    private $host;
    private $methods;
    private $security;
    private $userChecker;
    private $requestMatcher;
    private $accessDeniedUrl;
    private $accessDeniedHandler;
    private $entryPoint;
    private $provider;
    private $stateless;
    private $lazy;
    private $context;
    private $logout;
    private $switchUser;
    private $requiredBadges;
    private $customAuthenticators;
    private $loginThrottling;
    private $x509;
    private $remoteUser;
    private $loginLink;
    private $formLogin;
    private $formLoginLdap;
    private $jsonLogin;
    private $jsonLoginLdap;
    private $httpBasic;
    private $httpBasicLdap;
    private $rememberMe;
    private $_usedProperties = [];

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function pattern($value): static
    {
        $this->_usedProperties['pattern'] = true;
        $this->pattern = $value;

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
     * @param mixed $value
     *
     * @return $this
     */
    public function methods(mixed $value): static
    {
        $this->_usedProperties['methods'] = true;
        $this->methods = $value;

        return $this;
    }

    /**
     * @default true
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function security($value): static
    {
        $this->_usedProperties['security'] = true;
        $this->security = $value;

        return $this;
    }

    /**
     * The UserChecker to use when authenticating users in this firewall.
     * @default 'security.user_checker'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function userChecker($value): static
    {
        $this->_usedProperties['userChecker'] = true;
        $this->userChecker = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function requestMatcher($value): static
    {
        $this->_usedProperties['requestMatcher'] = true;
        $this->requestMatcher = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function accessDeniedUrl($value): static
    {
        $this->_usedProperties['accessDeniedUrl'] = true;
        $this->accessDeniedUrl = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function accessDeniedHandler($value): static
    {
        $this->_usedProperties['accessDeniedHandler'] = true;
        $this->accessDeniedHandler = $value;

        return $this;
    }

    /**
     * An enabled authenticator name or a service id that implements "Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface"
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function entryPoint($value): static
    {
        $this->_usedProperties['entryPoint'] = true;
        $this->entryPoint = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function provider($value): static
    {
        $this->_usedProperties['provider'] = true;
        $this->provider = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function stateless($value): static
    {
        $this->_usedProperties['stateless'] = true;
        $this->stateless = $value;

        return $this;
    }

    /**
     * @default false
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
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function context($value): static
    {
        $this->_usedProperties['context'] = true;
        $this->context = $value;

        return $this;
    }

    public function logout(array $value = []): \Symfony\Config\Security\FirewallConfig\LogoutConfig
    {
        if (null === $this->logout) {
            $this->_usedProperties['logout'] = true;
            $this->logout = new \Symfony\Config\Security\FirewallConfig\LogoutConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "logout()" has already been initialized. You cannot pass values the second time you call logout().');
        }

        return $this->logout;
    }

    public function switchUser(array $value = []): \Symfony\Config\Security\FirewallConfig\SwitchUserConfig
    {
        if (null === $this->switchUser) {
            $this->_usedProperties['switchUser'] = true;
            $this->switchUser = new \Symfony\Config\Security\FirewallConfig\SwitchUserConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "switchUser()" has already been initialized. You cannot pass values the second time you call switchUser().');
        }

        return $this->switchUser;
    }

    /**
     * @param ParamConfigurator|list<ParamConfigurator|mixed> $value
     *
     * @return $this
     */
    public function requiredBadges(ParamConfigurator|array $value): static
    {
        $this->_usedProperties['requiredBadges'] = true;
        $this->requiredBadges = $value;

        return $this;
    }

    /**
     * @param ParamConfigurator|list<ParamConfigurator|mixed> $value
     *
     * @return $this
     */
    public function customAuthenticators(ParamConfigurator|array $value): static
    {
        $this->_usedProperties['customAuthenticators'] = true;
        $this->customAuthenticators = $value;

        return $this;
    }

    public function loginThrottling(array $value = []): \Symfony\Config\Security\FirewallConfig\LoginThrottlingConfig
    {
        if (null === $this->loginThrottling) {
            $this->_usedProperties['loginThrottling'] = true;
            $this->loginThrottling = new \Symfony\Config\Security\FirewallConfig\LoginThrottlingConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "loginThrottling()" has already been initialized. You cannot pass values the second time you call loginThrottling().');
        }

        return $this->loginThrottling;
    }

    public function x509(array $value = []): \Symfony\Config\Security\FirewallConfig\X509Config
    {
        if (null === $this->x509) {
            $this->_usedProperties['x509'] = true;
            $this->x509 = new \Symfony\Config\Security\FirewallConfig\X509Config($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "x509()" has already been initialized. You cannot pass values the second time you call x509().');
        }

        return $this->x509;
    }

    public function remoteUser(array $value = []): \Symfony\Config\Security\FirewallConfig\RemoteUserConfig
    {
        if (null === $this->remoteUser) {
            $this->_usedProperties['remoteUser'] = true;
            $this->remoteUser = new \Symfony\Config\Security\FirewallConfig\RemoteUserConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "remoteUser()" has already been initialized. You cannot pass values the second time you call remoteUser().');
        }

        return $this->remoteUser;
    }

    public function loginLink(array $value = []): \Symfony\Config\Security\FirewallConfig\LoginLinkConfig
    {
        if (null === $this->loginLink) {
            $this->_usedProperties['loginLink'] = true;
            $this->loginLink = new \Symfony\Config\Security\FirewallConfig\LoginLinkConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "loginLink()" has already been initialized. You cannot pass values the second time you call loginLink().');
        }

        return $this->loginLink;
    }

    public function formLogin(array $value = []): \Symfony\Config\Security\FirewallConfig\FormLoginConfig
    {
        if (null === $this->formLogin) {
            $this->_usedProperties['formLogin'] = true;
            $this->formLogin = new \Symfony\Config\Security\FirewallConfig\FormLoginConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "formLogin()" has already been initialized. You cannot pass values the second time you call formLogin().');
        }

        return $this->formLogin;
    }

    public function formLoginLdap(array $value = []): \Symfony\Config\Security\FirewallConfig\FormLoginLdapConfig
    {
        if (null === $this->formLoginLdap) {
            $this->_usedProperties['formLoginLdap'] = true;
            $this->formLoginLdap = new \Symfony\Config\Security\FirewallConfig\FormLoginLdapConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "formLoginLdap()" has already been initialized. You cannot pass values the second time you call formLoginLdap().');
        }

        return $this->formLoginLdap;
    }

    public function jsonLogin(array $value = []): \Symfony\Config\Security\FirewallConfig\JsonLoginConfig
    {
        if (null === $this->jsonLogin) {
            $this->_usedProperties['jsonLogin'] = true;
            $this->jsonLogin = new \Symfony\Config\Security\FirewallConfig\JsonLoginConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "jsonLogin()" has already been initialized. You cannot pass values the second time you call jsonLogin().');
        }

        return $this->jsonLogin;
    }

    public function jsonLoginLdap(array $value = []): \Symfony\Config\Security\FirewallConfig\JsonLoginLdapConfig
    {
        if (null === $this->jsonLoginLdap) {
            $this->_usedProperties['jsonLoginLdap'] = true;
            $this->jsonLoginLdap = new \Symfony\Config\Security\FirewallConfig\JsonLoginLdapConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "jsonLoginLdap()" has already been initialized. You cannot pass values the second time you call jsonLoginLdap().');
        }

        return $this->jsonLoginLdap;
    }

    public function httpBasic(array $value = []): \Symfony\Config\Security\FirewallConfig\HttpBasicConfig
    {
        if (null === $this->httpBasic) {
            $this->_usedProperties['httpBasic'] = true;
            $this->httpBasic = new \Symfony\Config\Security\FirewallConfig\HttpBasicConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "httpBasic()" has already been initialized. You cannot pass values the second time you call httpBasic().');
        }

        return $this->httpBasic;
    }

    public function httpBasicLdap(array $value = []): \Symfony\Config\Security\FirewallConfig\HttpBasicLdapConfig
    {
        if (null === $this->httpBasicLdap) {
            $this->_usedProperties['httpBasicLdap'] = true;
            $this->httpBasicLdap = new \Symfony\Config\Security\FirewallConfig\HttpBasicLdapConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "httpBasicLdap()" has already been initialized. You cannot pass values the second time you call httpBasicLdap().');
        }

        return $this->httpBasicLdap;
    }

    public function rememberMe(array $value = []): \Symfony\Config\Security\FirewallConfig\RememberMeConfig
    {
        if (null === $this->rememberMe) {
            $this->_usedProperties['rememberMe'] = true;
            $this->rememberMe = new \Symfony\Config\Security\FirewallConfig\RememberMeConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "rememberMe()" has already been initialized. You cannot pass values the second time you call rememberMe().');
        }

        return $this->rememberMe;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('pattern', $value)) {
            $this->_usedProperties['pattern'] = true;
            $this->pattern = $value['pattern'];
            unset($value['pattern']);
        }

        if (array_key_exists('host', $value)) {
            $this->_usedProperties['host'] = true;
            $this->host = $value['host'];
            unset($value['host']);
        }

        if (array_key_exists('methods', $value)) {
            $this->_usedProperties['methods'] = true;
            $this->methods = $value['methods'];
            unset($value['methods']);
        }

        if (array_key_exists('security', $value)) {
            $this->_usedProperties['security'] = true;
            $this->security = $value['security'];
            unset($value['security']);
        }

        if (array_key_exists('user_checker', $value)) {
            $this->_usedProperties['userChecker'] = true;
            $this->userChecker = $value['user_checker'];
            unset($value['user_checker']);
        }

        if (array_key_exists('request_matcher', $value)) {
            $this->_usedProperties['requestMatcher'] = true;
            $this->requestMatcher = $value['request_matcher'];
            unset($value['request_matcher']);
        }

        if (array_key_exists('access_denied_url', $value)) {
            $this->_usedProperties['accessDeniedUrl'] = true;
            $this->accessDeniedUrl = $value['access_denied_url'];
            unset($value['access_denied_url']);
        }

        if (array_key_exists('access_denied_handler', $value)) {
            $this->_usedProperties['accessDeniedHandler'] = true;
            $this->accessDeniedHandler = $value['access_denied_handler'];
            unset($value['access_denied_handler']);
        }

        if (array_key_exists('entry_point', $value)) {
            $this->_usedProperties['entryPoint'] = true;
            $this->entryPoint = $value['entry_point'];
            unset($value['entry_point']);
        }

        if (array_key_exists('provider', $value)) {
            $this->_usedProperties['provider'] = true;
            $this->provider = $value['provider'];
            unset($value['provider']);
        }

        if (array_key_exists('stateless', $value)) {
            $this->_usedProperties['stateless'] = true;
            $this->stateless = $value['stateless'];
            unset($value['stateless']);
        }

        if (array_key_exists('lazy', $value)) {
            $this->_usedProperties['lazy'] = true;
            $this->lazy = $value['lazy'];
            unset($value['lazy']);
        }

        if (array_key_exists('context', $value)) {
            $this->_usedProperties['context'] = true;
            $this->context = $value['context'];
            unset($value['context']);
        }

        if (array_key_exists('logout', $value)) {
            $this->_usedProperties['logout'] = true;
            $this->logout = new \Symfony\Config\Security\FirewallConfig\LogoutConfig($value['logout']);
            unset($value['logout']);
        }

        if (array_key_exists('switch_user', $value)) {
            $this->_usedProperties['switchUser'] = true;
            $this->switchUser = new \Symfony\Config\Security\FirewallConfig\SwitchUserConfig($value['switch_user']);
            unset($value['switch_user']);
        }

        if (array_key_exists('required_badges', $value)) {
            $this->_usedProperties['requiredBadges'] = true;
            $this->requiredBadges = $value['required_badges'];
            unset($value['required_badges']);
        }

        if (array_key_exists('custom_authenticators', $value)) {
            $this->_usedProperties['customAuthenticators'] = true;
            $this->customAuthenticators = $value['custom_authenticators'];
            unset($value['custom_authenticators']);
        }

        if (array_key_exists('login_throttling', $value)) {
            $this->_usedProperties['loginThrottling'] = true;
            $this->loginThrottling = new \Symfony\Config\Security\FirewallConfig\LoginThrottlingConfig($value['login_throttling']);
            unset($value['login_throttling']);
        }

        if (array_key_exists('x509', $value)) {
            $this->_usedProperties['x509'] = true;
            $this->x509 = new \Symfony\Config\Security\FirewallConfig\X509Config($value['x509']);
            unset($value['x509']);
        }

        if (array_key_exists('remote_user', $value)) {
            $this->_usedProperties['remoteUser'] = true;
            $this->remoteUser = new \Symfony\Config\Security\FirewallConfig\RemoteUserConfig($value['remote_user']);
            unset($value['remote_user']);
        }

        if (array_key_exists('login_link', $value)) {
            $this->_usedProperties['loginLink'] = true;
            $this->loginLink = new \Symfony\Config\Security\FirewallConfig\LoginLinkConfig($value['login_link']);
            unset($value['login_link']);
        }

        if (array_key_exists('form_login', $value)) {
            $this->_usedProperties['formLogin'] = true;
            $this->formLogin = new \Symfony\Config\Security\FirewallConfig\FormLoginConfig($value['form_login']);
            unset($value['form_login']);
        }

        if (array_key_exists('form_login_ldap', $value)) {
            $this->_usedProperties['formLoginLdap'] = true;
            $this->formLoginLdap = new \Symfony\Config\Security\FirewallConfig\FormLoginLdapConfig($value['form_login_ldap']);
            unset($value['form_login_ldap']);
        }

        if (array_key_exists('json_login', $value)) {
            $this->_usedProperties['jsonLogin'] = true;
            $this->jsonLogin = new \Symfony\Config\Security\FirewallConfig\JsonLoginConfig($value['json_login']);
            unset($value['json_login']);
        }

        if (array_key_exists('json_login_ldap', $value)) {
            $this->_usedProperties['jsonLoginLdap'] = true;
            $this->jsonLoginLdap = new \Symfony\Config\Security\FirewallConfig\JsonLoginLdapConfig($value['json_login_ldap']);
            unset($value['json_login_ldap']);
        }

        if (array_key_exists('http_basic', $value)) {
            $this->_usedProperties['httpBasic'] = true;
            $this->httpBasic = new \Symfony\Config\Security\FirewallConfig\HttpBasicConfig($value['http_basic']);
            unset($value['http_basic']);
        }

        if (array_key_exists('http_basic_ldap', $value)) {
            $this->_usedProperties['httpBasicLdap'] = true;
            $this->httpBasicLdap = new \Symfony\Config\Security\FirewallConfig\HttpBasicLdapConfig($value['http_basic_ldap']);
            unset($value['http_basic_ldap']);
        }

        if (array_key_exists('remember_me', $value)) {
            $this->_usedProperties['rememberMe'] = true;
            $this->rememberMe = new \Symfony\Config\Security\FirewallConfig\RememberMeConfig($value['remember_me']);
            unset($value['remember_me']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['pattern'])) {
            $output['pattern'] = $this->pattern;
        }
        if (isset($this->_usedProperties['host'])) {
            $output['host'] = $this->host;
        }
        if (isset($this->_usedProperties['methods'])) {
            $output['methods'] = $this->methods;
        }
        if (isset($this->_usedProperties['security'])) {
            $output['security'] = $this->security;
        }
        if (isset($this->_usedProperties['userChecker'])) {
            $output['user_checker'] = $this->userChecker;
        }
        if (isset($this->_usedProperties['requestMatcher'])) {
            $output['request_matcher'] = $this->requestMatcher;
        }
        if (isset($this->_usedProperties['accessDeniedUrl'])) {
            $output['access_denied_url'] = $this->accessDeniedUrl;
        }
        if (isset($this->_usedProperties['accessDeniedHandler'])) {
            $output['access_denied_handler'] = $this->accessDeniedHandler;
        }
        if (isset($this->_usedProperties['entryPoint'])) {
            $output['entry_point'] = $this->entryPoint;
        }
        if (isset($this->_usedProperties['provider'])) {
            $output['provider'] = $this->provider;
        }
        if (isset($this->_usedProperties['stateless'])) {
            $output['stateless'] = $this->stateless;
        }
        if (isset($this->_usedProperties['lazy'])) {
            $output['lazy'] = $this->lazy;
        }
        if (isset($this->_usedProperties['context'])) {
            $output['context'] = $this->context;
        }
        if (isset($this->_usedProperties['logout'])) {
            $output['logout'] = $this->logout->toArray();
        }
        if (isset($this->_usedProperties['switchUser'])) {
            $output['switch_user'] = $this->switchUser->toArray();
        }
        if (isset($this->_usedProperties['requiredBadges'])) {
            $output['required_badges'] = $this->requiredBadges;
        }
        if (isset($this->_usedProperties['customAuthenticators'])) {
            $output['custom_authenticators'] = $this->customAuthenticators;
        }
        if (isset($this->_usedProperties['loginThrottling'])) {
            $output['login_throttling'] = $this->loginThrottling->toArray();
        }
        if (isset($this->_usedProperties['x509'])) {
            $output['x509'] = $this->x509->toArray();
        }
        if (isset($this->_usedProperties['remoteUser'])) {
            $output['remote_user'] = $this->remoteUser->toArray();
        }
        if (isset($this->_usedProperties['loginLink'])) {
            $output['login_link'] = $this->loginLink->toArray();
        }
        if (isset($this->_usedProperties['formLogin'])) {
            $output['form_login'] = $this->formLogin->toArray();
        }
        if (isset($this->_usedProperties['formLoginLdap'])) {
            $output['form_login_ldap'] = $this->formLoginLdap->toArray();
        }
        if (isset($this->_usedProperties['jsonLogin'])) {
            $output['json_login'] = $this->jsonLogin->toArray();
        }
        if (isset($this->_usedProperties['jsonLoginLdap'])) {
            $output['json_login_ldap'] = $this->jsonLoginLdap->toArray();
        }
        if (isset($this->_usedProperties['httpBasic'])) {
            $output['http_basic'] = $this->httpBasic->toArray();
        }
        if (isset($this->_usedProperties['httpBasicLdap'])) {
            $output['http_basic_ldap'] = $this->httpBasicLdap->toArray();
        }
        if (isset($this->_usedProperties['rememberMe'])) {
            $output['remember_me'] = $this->rememberMe->toArray();
        }

        return $output;
    }

}
