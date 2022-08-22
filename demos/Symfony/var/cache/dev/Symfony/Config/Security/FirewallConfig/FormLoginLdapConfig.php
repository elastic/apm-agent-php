<?php

namespace Symfony\Config\Security\FirewallConfig;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class FormLoginLdapConfig 
{
    private $provider;
    private $rememberMe;
    private $successHandler;
    private $failureHandler;
    private $checkPath;
    private $useForward;
    private $requirePreviousSession;
    private $loginPath;
    private $usernameParameter;
    private $passwordParameter;
    private $csrfParameter;
    private $csrfTokenId;
    private $enableCsrf;
    private $postOnly;
    private $formOnly;
    private $alwaysUseDefaultTargetPath;
    private $defaultTargetPath;
    private $targetPathParameter;
    private $useReferer;
    private $failurePath;
    private $failureForward;
    private $failurePathParameter;
    private $csrfTokenGenerator;
    private $service;
    private $dnString;
    private $queryString;
    private $searchDn;
    private $searchPassword;
    private $_usedProperties = [];

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
     * @default true
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function rememberMe($value): static
    {
        $this->_usedProperties['rememberMe'] = true;
        $this->rememberMe = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function successHandler($value): static
    {
        $this->_usedProperties['successHandler'] = true;
        $this->successHandler = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function failureHandler($value): static
    {
        $this->_usedProperties['failureHandler'] = true;
        $this->failureHandler = $value;

        return $this;
    }

    /**
     * @default '/login_check'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function checkPath($value): static
    {
        $this->_usedProperties['checkPath'] = true;
        $this->checkPath = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function useForward($value): static
    {
        $this->_usedProperties['useForward'] = true;
        $this->useForward = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function requirePreviousSession($value): static
    {
        $this->_usedProperties['requirePreviousSession'] = true;
        $this->requirePreviousSession = $value;

        return $this;
    }

    /**
     * @default '/login'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function loginPath($value): static
    {
        $this->_usedProperties['loginPath'] = true;
        $this->loginPath = $value;

        return $this;
    }

    /**
     * @default '_username'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function usernameParameter($value): static
    {
        $this->_usedProperties['usernameParameter'] = true;
        $this->usernameParameter = $value;

        return $this;
    }

    /**
     * @default '_password'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function passwordParameter($value): static
    {
        $this->_usedProperties['passwordParameter'] = true;
        $this->passwordParameter = $value;

        return $this;
    }

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
     * @default 'authenticate'
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
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function enableCsrf($value): static
    {
        $this->_usedProperties['enableCsrf'] = true;
        $this->enableCsrf = $value;

        return $this;
    }

    /**
     * @default true
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function postOnly($value): static
    {
        $this->_usedProperties['postOnly'] = true;
        $this->postOnly = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function formOnly($value): static
    {
        $this->_usedProperties['formOnly'] = true;
        $this->formOnly = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function alwaysUseDefaultTargetPath($value): static
    {
        $this->_usedProperties['alwaysUseDefaultTargetPath'] = true;
        $this->alwaysUseDefaultTargetPath = $value;

        return $this;
    }

    /**
     * @default '/'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function defaultTargetPath($value): static
    {
        $this->_usedProperties['defaultTargetPath'] = true;
        $this->defaultTargetPath = $value;

        return $this;
    }

    /**
     * @default '_target_path'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function targetPathParameter($value): static
    {
        $this->_usedProperties['targetPathParameter'] = true;
        $this->targetPathParameter = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function useReferer($value): static
    {
        $this->_usedProperties['useReferer'] = true;
        $this->useReferer = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function failurePath($value): static
    {
        $this->_usedProperties['failurePath'] = true;
        $this->failurePath = $value;

        return $this;
    }

    /**
     * @default false
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function failureForward($value): static
    {
        $this->_usedProperties['failureForward'] = true;
        $this->failureForward = $value;

        return $this;
    }

    /**
     * @default '_failure_path'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function failurePathParameter($value): static
    {
        $this->_usedProperties['failurePathParameter'] = true;
        $this->failurePathParameter = $value;

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
     * @default 'ldap'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function service($value): static
    {
        $this->_usedProperties['service'] = true;
        $this->service = $value;

        return $this;
    }

    /**
     * @default '{username}'
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function dnString($value): static
    {
        $this->_usedProperties['dnString'] = true;
        $this->dnString = $value;

        return $this;
    }

    /**
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function queryString($value): static
    {
        $this->_usedProperties['queryString'] = true;
        $this->queryString = $value;

        return $this;
    }

    /**
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function searchDn($value): static
    {
        $this->_usedProperties['searchDn'] = true;
        $this->searchDn = $value;

        return $this;
    }

    /**
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function searchPassword($value): static
    {
        $this->_usedProperties['searchPassword'] = true;
        $this->searchPassword = $value;

        return $this;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('provider', $value)) {
            $this->_usedProperties['provider'] = true;
            $this->provider = $value['provider'];
            unset($value['provider']);
        }

        if (array_key_exists('remember_me', $value)) {
            $this->_usedProperties['rememberMe'] = true;
            $this->rememberMe = $value['remember_me'];
            unset($value['remember_me']);
        }

        if (array_key_exists('success_handler', $value)) {
            $this->_usedProperties['successHandler'] = true;
            $this->successHandler = $value['success_handler'];
            unset($value['success_handler']);
        }

        if (array_key_exists('failure_handler', $value)) {
            $this->_usedProperties['failureHandler'] = true;
            $this->failureHandler = $value['failure_handler'];
            unset($value['failure_handler']);
        }

        if (array_key_exists('check_path', $value)) {
            $this->_usedProperties['checkPath'] = true;
            $this->checkPath = $value['check_path'];
            unset($value['check_path']);
        }

        if (array_key_exists('use_forward', $value)) {
            $this->_usedProperties['useForward'] = true;
            $this->useForward = $value['use_forward'];
            unset($value['use_forward']);
        }

        if (array_key_exists('require_previous_session', $value)) {
            $this->_usedProperties['requirePreviousSession'] = true;
            $this->requirePreviousSession = $value['require_previous_session'];
            unset($value['require_previous_session']);
        }

        if (array_key_exists('login_path', $value)) {
            $this->_usedProperties['loginPath'] = true;
            $this->loginPath = $value['login_path'];
            unset($value['login_path']);
        }

        if (array_key_exists('username_parameter', $value)) {
            $this->_usedProperties['usernameParameter'] = true;
            $this->usernameParameter = $value['username_parameter'];
            unset($value['username_parameter']);
        }

        if (array_key_exists('password_parameter', $value)) {
            $this->_usedProperties['passwordParameter'] = true;
            $this->passwordParameter = $value['password_parameter'];
            unset($value['password_parameter']);
        }

        if (array_key_exists('csrf_parameter', $value)) {
            $this->_usedProperties['csrfParameter'] = true;
            $this->csrfParameter = $value['csrf_parameter'];
            unset($value['csrf_parameter']);
        }

        if (array_key_exists('csrf_token_id', $value)) {
            $this->_usedProperties['csrfTokenId'] = true;
            $this->csrfTokenId = $value['csrf_token_id'];
            unset($value['csrf_token_id']);
        }

        if (array_key_exists('enable_csrf', $value)) {
            $this->_usedProperties['enableCsrf'] = true;
            $this->enableCsrf = $value['enable_csrf'];
            unset($value['enable_csrf']);
        }

        if (array_key_exists('post_only', $value)) {
            $this->_usedProperties['postOnly'] = true;
            $this->postOnly = $value['post_only'];
            unset($value['post_only']);
        }

        if (array_key_exists('form_only', $value)) {
            $this->_usedProperties['formOnly'] = true;
            $this->formOnly = $value['form_only'];
            unset($value['form_only']);
        }

        if (array_key_exists('always_use_default_target_path', $value)) {
            $this->_usedProperties['alwaysUseDefaultTargetPath'] = true;
            $this->alwaysUseDefaultTargetPath = $value['always_use_default_target_path'];
            unset($value['always_use_default_target_path']);
        }

        if (array_key_exists('default_target_path', $value)) {
            $this->_usedProperties['defaultTargetPath'] = true;
            $this->defaultTargetPath = $value['default_target_path'];
            unset($value['default_target_path']);
        }

        if (array_key_exists('target_path_parameter', $value)) {
            $this->_usedProperties['targetPathParameter'] = true;
            $this->targetPathParameter = $value['target_path_parameter'];
            unset($value['target_path_parameter']);
        }

        if (array_key_exists('use_referer', $value)) {
            $this->_usedProperties['useReferer'] = true;
            $this->useReferer = $value['use_referer'];
            unset($value['use_referer']);
        }

        if (array_key_exists('failure_path', $value)) {
            $this->_usedProperties['failurePath'] = true;
            $this->failurePath = $value['failure_path'];
            unset($value['failure_path']);
        }

        if (array_key_exists('failure_forward', $value)) {
            $this->_usedProperties['failureForward'] = true;
            $this->failureForward = $value['failure_forward'];
            unset($value['failure_forward']);
        }

        if (array_key_exists('failure_path_parameter', $value)) {
            $this->_usedProperties['failurePathParameter'] = true;
            $this->failurePathParameter = $value['failure_path_parameter'];
            unset($value['failure_path_parameter']);
        }

        if (array_key_exists('csrf_token_generator', $value)) {
            $this->_usedProperties['csrfTokenGenerator'] = true;
            $this->csrfTokenGenerator = $value['csrf_token_generator'];
            unset($value['csrf_token_generator']);
        }

        if (array_key_exists('service', $value)) {
            $this->_usedProperties['service'] = true;
            $this->service = $value['service'];
            unset($value['service']);
        }

        if (array_key_exists('dn_string', $value)) {
            $this->_usedProperties['dnString'] = true;
            $this->dnString = $value['dn_string'];
            unset($value['dn_string']);
        }

        if (array_key_exists('query_string', $value)) {
            $this->_usedProperties['queryString'] = true;
            $this->queryString = $value['query_string'];
            unset($value['query_string']);
        }

        if (array_key_exists('search_dn', $value)) {
            $this->_usedProperties['searchDn'] = true;
            $this->searchDn = $value['search_dn'];
            unset($value['search_dn']);
        }

        if (array_key_exists('search_password', $value)) {
            $this->_usedProperties['searchPassword'] = true;
            $this->searchPassword = $value['search_password'];
            unset($value['search_password']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['provider'])) {
            $output['provider'] = $this->provider;
        }
        if (isset($this->_usedProperties['rememberMe'])) {
            $output['remember_me'] = $this->rememberMe;
        }
        if (isset($this->_usedProperties['successHandler'])) {
            $output['success_handler'] = $this->successHandler;
        }
        if (isset($this->_usedProperties['failureHandler'])) {
            $output['failure_handler'] = $this->failureHandler;
        }
        if (isset($this->_usedProperties['checkPath'])) {
            $output['check_path'] = $this->checkPath;
        }
        if (isset($this->_usedProperties['useForward'])) {
            $output['use_forward'] = $this->useForward;
        }
        if (isset($this->_usedProperties['requirePreviousSession'])) {
            $output['require_previous_session'] = $this->requirePreviousSession;
        }
        if (isset($this->_usedProperties['loginPath'])) {
            $output['login_path'] = $this->loginPath;
        }
        if (isset($this->_usedProperties['usernameParameter'])) {
            $output['username_parameter'] = $this->usernameParameter;
        }
        if (isset($this->_usedProperties['passwordParameter'])) {
            $output['password_parameter'] = $this->passwordParameter;
        }
        if (isset($this->_usedProperties['csrfParameter'])) {
            $output['csrf_parameter'] = $this->csrfParameter;
        }
        if (isset($this->_usedProperties['csrfTokenId'])) {
            $output['csrf_token_id'] = $this->csrfTokenId;
        }
        if (isset($this->_usedProperties['enableCsrf'])) {
            $output['enable_csrf'] = $this->enableCsrf;
        }
        if (isset($this->_usedProperties['postOnly'])) {
            $output['post_only'] = $this->postOnly;
        }
        if (isset($this->_usedProperties['formOnly'])) {
            $output['form_only'] = $this->formOnly;
        }
        if (isset($this->_usedProperties['alwaysUseDefaultTargetPath'])) {
            $output['always_use_default_target_path'] = $this->alwaysUseDefaultTargetPath;
        }
        if (isset($this->_usedProperties['defaultTargetPath'])) {
            $output['default_target_path'] = $this->defaultTargetPath;
        }
        if (isset($this->_usedProperties['targetPathParameter'])) {
            $output['target_path_parameter'] = $this->targetPathParameter;
        }
        if (isset($this->_usedProperties['useReferer'])) {
            $output['use_referer'] = $this->useReferer;
        }
        if (isset($this->_usedProperties['failurePath'])) {
            $output['failure_path'] = $this->failurePath;
        }
        if (isset($this->_usedProperties['failureForward'])) {
            $output['failure_forward'] = $this->failureForward;
        }
        if (isset($this->_usedProperties['failurePathParameter'])) {
            $output['failure_path_parameter'] = $this->failurePathParameter;
        }
        if (isset($this->_usedProperties['csrfTokenGenerator'])) {
            $output['csrf_token_generator'] = $this->csrfTokenGenerator;
        }
        if (isset($this->_usedProperties['service'])) {
            $output['service'] = $this->service;
        }
        if (isset($this->_usedProperties['dnString'])) {
            $output['dn_string'] = $this->dnString;
        }
        if (isset($this->_usedProperties['queryString'])) {
            $output['query_string'] = $this->queryString;
        }
        if (isset($this->_usedProperties['searchDn'])) {
            $output['search_dn'] = $this->searchDn;
        }
        if (isset($this->_usedProperties['searchPassword'])) {
            $output['search_password'] = $this->searchPassword;
        }

        return $output;
    }

}
