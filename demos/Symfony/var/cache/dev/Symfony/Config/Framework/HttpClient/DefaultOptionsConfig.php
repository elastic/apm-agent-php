<?php

namespace Symfony\Config\Framework\HttpClient;

require_once __DIR__.\DIRECTORY_SEPARATOR.'DefaultOptions'.\DIRECTORY_SEPARATOR.'PeerFingerprintConfig.php';
require_once __DIR__.\DIRECTORY_SEPARATOR.'DefaultOptions'.\DIRECTORY_SEPARATOR.'RetryFailedConfig.php';

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This class is automatically generated to help in creating a config.
 */
class DefaultOptionsConfig 
{
    private $headers;
    private $maxRedirects;
    private $httpVersion;
    private $resolve;
    private $proxy;
    private $noProxy;
    private $timeout;
    private $maxDuration;
    private $bindto;
    private $verifyPeer;
    private $verifyHost;
    private $cafile;
    private $capath;
    private $localCert;
    private $localPk;
    private $passphrase;
    private $ciphers;
    private $peerFingerprint;
    private $retryFailed;
    private $_usedProperties = [];

    /**
     * @return $this
     */
    public function header(string $name, mixed $value): static
    {
        $this->_usedProperties['headers'] = true;
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * The maximum number of redirects to follow.
     * @default null
     * @param ParamConfigurator|int $value
     * @return $this
     */
    public function maxRedirects($value): static
    {
        $this->_usedProperties['maxRedirects'] = true;
        $this->maxRedirects = $value;

        return $this;
    }

    /**
     * The default HTTP version, typically 1.1 or 2.0, leave to null for the best version.
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function httpVersion($value): static
    {
        $this->_usedProperties['httpVersion'] = true;
        $this->httpVersion = $value;

        return $this;
    }

    /**
     * @return $this
     */
    public function resolve(string $host, mixed $value): static
    {
        $this->_usedProperties['resolve'] = true;
        $this->resolve[$host] = $value;

        return $this;
    }

    /**
     * The URL of the proxy to pass requests through or null for automatic detection.
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function proxy($value): static
    {
        $this->_usedProperties['proxy'] = true;
        $this->proxy = $value;

        return $this;
    }

    /**
     * A comma separated list of hosts that do not require a proxy to be reached.
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function noProxy($value): static
    {
        $this->_usedProperties['noProxy'] = true;
        $this->noProxy = $value;

        return $this;
    }

    /**
     * The idle timeout, defaults to the "default_socket_timeout" ini parameter.
     * @default null
     * @param ParamConfigurator|float $value
     * @return $this
     */
    public function timeout($value): static
    {
        $this->_usedProperties['timeout'] = true;
        $this->timeout = $value;

        return $this;
    }

    /**
     * The maximum execution time for the request+response as a whole.
     * @default null
     * @param ParamConfigurator|float $value
     * @return $this
     */
    public function maxDuration($value): static
    {
        $this->_usedProperties['maxDuration'] = true;
        $this->maxDuration = $value;

        return $this;
    }

    /**
     * A network interface name, IP address, a host name or a UNIX socket to bind to.
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function bindto($value): static
    {
        $this->_usedProperties['bindto'] = true;
        $this->bindto = $value;

        return $this;
    }

    /**
     * Indicates if the peer should be verified in an SSL/TLS context.
     * @default null
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function verifyPeer($value): static
    {
        $this->_usedProperties['verifyPeer'] = true;
        $this->verifyPeer = $value;

        return $this;
    }

    /**
     * Indicates if the host should exist as a certificate common name.
     * @default null
     * @param ParamConfigurator|bool $value
     * @return $this
     */
    public function verifyHost($value): static
    {
        $this->_usedProperties['verifyHost'] = true;
        $this->verifyHost = $value;

        return $this;
    }

    /**
     * A certificate authority file.
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function cafile($value): static
    {
        $this->_usedProperties['cafile'] = true;
        $this->cafile = $value;

        return $this;
    }

    /**
     * A directory that contains multiple certificate authority files.
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function capath($value): static
    {
        $this->_usedProperties['capath'] = true;
        $this->capath = $value;

        return $this;
    }

    /**
     * A PEM formatted certificate file.
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function localCert($value): static
    {
        $this->_usedProperties['localCert'] = true;
        $this->localCert = $value;

        return $this;
    }

    /**
     * A private key file.
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function localPk($value): static
    {
        $this->_usedProperties['localPk'] = true;
        $this->localPk = $value;

        return $this;
    }

    /**
     * The passphrase used to encrypt the "local_pk" file.
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function passphrase($value): static
    {
        $this->_usedProperties['passphrase'] = true;
        $this->passphrase = $value;

        return $this;
    }

    /**
     * A list of SSL/TLS ciphers separated by colons, commas or spaces (e.g. "RC3-SHA:TLS13-AES-128-GCM-SHA256"...)
     * @default null
     * @param ParamConfigurator|mixed $value
     * @return $this
     */
    public function ciphers($value): static
    {
        $this->_usedProperties['ciphers'] = true;
        $this->ciphers = $value;

        return $this;
    }

    /**
     * Associative array: hashing algorithm => hash(es).
    */
    public function peerFingerprint(array $value = []): \Symfony\Config\Framework\HttpClient\DefaultOptions\PeerFingerprintConfig
    {
        if (null === $this->peerFingerprint) {
            $this->_usedProperties['peerFingerprint'] = true;
            $this->peerFingerprint = new \Symfony\Config\Framework\HttpClient\DefaultOptions\PeerFingerprintConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "peerFingerprint()" has already been initialized. You cannot pass values the second time you call peerFingerprint().');
        }

        return $this->peerFingerprint;
    }

    /**
     * @default {"enabled":false,"retry_strategy":null,"http_codes":[],"max_retries":3,"delay":1000,"multiplier":2,"max_delay":0,"jitter":0.1}
     * @return \Symfony\Config\Framework\HttpClient\DefaultOptions\RetryFailedConfig|$this
     */
    public function retryFailed(mixed $value = []): \Symfony\Config\Framework\HttpClient\DefaultOptions\RetryFailedConfig|static
    {
        if (!\is_array($value)) {
            $this->_usedProperties['retryFailed'] = true;
            $this->retryFailed = $value;

            return $this;
        }

        if (!$this->retryFailed instanceof \Symfony\Config\Framework\HttpClient\DefaultOptions\RetryFailedConfig) {
            $this->_usedProperties['retryFailed'] = true;
            $this->retryFailed = new \Symfony\Config\Framework\HttpClient\DefaultOptions\RetryFailedConfig($value);
        } elseif (0 < \func_num_args()) {
            throw new InvalidConfigurationException('The node created by "retryFailed()" has already been initialized. You cannot pass values the second time you call retryFailed().');
        }

        return $this->retryFailed;
    }

    public function __construct(array $value = [])
    {
        if (array_key_exists('headers', $value)) {
            $this->_usedProperties['headers'] = true;
            $this->headers = $value['headers'];
            unset($value['headers']);
        }

        if (array_key_exists('max_redirects', $value)) {
            $this->_usedProperties['maxRedirects'] = true;
            $this->maxRedirects = $value['max_redirects'];
            unset($value['max_redirects']);
        }

        if (array_key_exists('http_version', $value)) {
            $this->_usedProperties['httpVersion'] = true;
            $this->httpVersion = $value['http_version'];
            unset($value['http_version']);
        }

        if (array_key_exists('resolve', $value)) {
            $this->_usedProperties['resolve'] = true;
            $this->resolve = $value['resolve'];
            unset($value['resolve']);
        }

        if (array_key_exists('proxy', $value)) {
            $this->_usedProperties['proxy'] = true;
            $this->proxy = $value['proxy'];
            unset($value['proxy']);
        }

        if (array_key_exists('no_proxy', $value)) {
            $this->_usedProperties['noProxy'] = true;
            $this->noProxy = $value['no_proxy'];
            unset($value['no_proxy']);
        }

        if (array_key_exists('timeout', $value)) {
            $this->_usedProperties['timeout'] = true;
            $this->timeout = $value['timeout'];
            unset($value['timeout']);
        }

        if (array_key_exists('max_duration', $value)) {
            $this->_usedProperties['maxDuration'] = true;
            $this->maxDuration = $value['max_duration'];
            unset($value['max_duration']);
        }

        if (array_key_exists('bindto', $value)) {
            $this->_usedProperties['bindto'] = true;
            $this->bindto = $value['bindto'];
            unset($value['bindto']);
        }

        if (array_key_exists('verify_peer', $value)) {
            $this->_usedProperties['verifyPeer'] = true;
            $this->verifyPeer = $value['verify_peer'];
            unset($value['verify_peer']);
        }

        if (array_key_exists('verify_host', $value)) {
            $this->_usedProperties['verifyHost'] = true;
            $this->verifyHost = $value['verify_host'];
            unset($value['verify_host']);
        }

        if (array_key_exists('cafile', $value)) {
            $this->_usedProperties['cafile'] = true;
            $this->cafile = $value['cafile'];
            unset($value['cafile']);
        }

        if (array_key_exists('capath', $value)) {
            $this->_usedProperties['capath'] = true;
            $this->capath = $value['capath'];
            unset($value['capath']);
        }

        if (array_key_exists('local_cert', $value)) {
            $this->_usedProperties['localCert'] = true;
            $this->localCert = $value['local_cert'];
            unset($value['local_cert']);
        }

        if (array_key_exists('local_pk', $value)) {
            $this->_usedProperties['localPk'] = true;
            $this->localPk = $value['local_pk'];
            unset($value['local_pk']);
        }

        if (array_key_exists('passphrase', $value)) {
            $this->_usedProperties['passphrase'] = true;
            $this->passphrase = $value['passphrase'];
            unset($value['passphrase']);
        }

        if (array_key_exists('ciphers', $value)) {
            $this->_usedProperties['ciphers'] = true;
            $this->ciphers = $value['ciphers'];
            unset($value['ciphers']);
        }

        if (array_key_exists('peer_fingerprint', $value)) {
            $this->_usedProperties['peerFingerprint'] = true;
            $this->peerFingerprint = new \Symfony\Config\Framework\HttpClient\DefaultOptions\PeerFingerprintConfig($value['peer_fingerprint']);
            unset($value['peer_fingerprint']);
        }

        if (array_key_exists('retry_failed', $value)) {
            $this->_usedProperties['retryFailed'] = true;
            $this->retryFailed = \is_array($value['retry_failed']) ? new \Symfony\Config\Framework\HttpClient\DefaultOptions\RetryFailedConfig($value['retry_failed']) : $value['retry_failed'];
            unset($value['retry_failed']);
        }

        if ([] !== $value) {
            throw new InvalidConfigurationException(sprintf('The following keys are not supported by "%s": ', __CLASS__).implode(', ', array_keys($value)));
        }
    }

    public function toArray(): array
    {
        $output = [];
        if (isset($this->_usedProperties['headers'])) {
            $output['headers'] = $this->headers;
        }
        if (isset($this->_usedProperties['maxRedirects'])) {
            $output['max_redirects'] = $this->maxRedirects;
        }
        if (isset($this->_usedProperties['httpVersion'])) {
            $output['http_version'] = $this->httpVersion;
        }
        if (isset($this->_usedProperties['resolve'])) {
            $output['resolve'] = $this->resolve;
        }
        if (isset($this->_usedProperties['proxy'])) {
            $output['proxy'] = $this->proxy;
        }
        if (isset($this->_usedProperties['noProxy'])) {
            $output['no_proxy'] = $this->noProxy;
        }
        if (isset($this->_usedProperties['timeout'])) {
            $output['timeout'] = $this->timeout;
        }
        if (isset($this->_usedProperties['maxDuration'])) {
            $output['max_duration'] = $this->maxDuration;
        }
        if (isset($this->_usedProperties['bindto'])) {
            $output['bindto'] = $this->bindto;
        }
        if (isset($this->_usedProperties['verifyPeer'])) {
            $output['verify_peer'] = $this->verifyPeer;
        }
        if (isset($this->_usedProperties['verifyHost'])) {
            $output['verify_host'] = $this->verifyHost;
        }
        if (isset($this->_usedProperties['cafile'])) {
            $output['cafile'] = $this->cafile;
        }
        if (isset($this->_usedProperties['capath'])) {
            $output['capath'] = $this->capath;
        }
        if (isset($this->_usedProperties['localCert'])) {
            $output['local_cert'] = $this->localCert;
        }
        if (isset($this->_usedProperties['localPk'])) {
            $output['local_pk'] = $this->localPk;
        }
        if (isset($this->_usedProperties['passphrase'])) {
            $output['passphrase'] = $this->passphrase;
        }
        if (isset($this->_usedProperties['ciphers'])) {
            $output['ciphers'] = $this->ciphers;
        }
        if (isset($this->_usedProperties['peerFingerprint'])) {
            $output['peer_fingerprint'] = $this->peerFingerprint->toArray();
        }
        if (isset($this->_usedProperties['retryFailed'])) {
            $output['retry_failed'] = $this->retryFailed instanceof \Symfony\Config\Framework\HttpClient\DefaultOptions\RetryFailedConfig ? $this->retryFailed->toArray() : $this->retryFailed;
        }

        return $output;
    }

}
