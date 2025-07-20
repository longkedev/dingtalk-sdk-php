<?php

declare(strict_types=1);

namespace DingTalk\Auth;

use DingTalk\Contracts\AuthInterface;
use DingTalk\Contracts\ConfigInterface;
use DingTalk\Contracts\HttpClientInterface;
use DingTalk\Contracts\CacheInterface;
use DingTalk\Exceptions\AuthException;

/**
 * 认证管理器
 * 
 * 负责处理钉钉API的认证授权
 */
class AuthManager implements AuthInterface
{
    /**
     * 配置管理器
     */
    private ConfigInterface $config;

    /**
     * HTTP客户端
     */
    private HttpClientInterface $httpClient;

    /**
     * 缓存管理器
     */
    private CacheInterface $cache;

    /**
     * 访问令牌缓存键
     */
    private const ACCESS_TOKEN_CACHE_KEY = 'access_token';

    /**
     * 构造函数
     */
    public function __construct(
        ConfigInterface $config,
        HttpClientInterface $httpClient,
        CacheInterface $cache
    ) {
        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->cache = $cache;
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessToken(bool $refresh = false): string
    {
        $cacheKey = $this->getAccessTokenCacheKey();
        
        if (!$refresh && $this->cache->has($cacheKey)) {
            $token = $this->cache->get($cacheKey);
            if ($this->isTokenValid($token)) {
                return $token;
            }
        }

        return $this->refreshAccessToken();
    }

    /**
     * {@inheritdoc}
     */
    public function refreshAccessToken(): string
    {
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            return $this->getV2AccessToken();
        }
        
        return $this->getV1AccessToken();
    }

    /**
     * {@inheritdoc}
     */
    public function isTokenValid(?string $token = null): bool
    {
        if ($token === null) {
            $token = $this->cache->get($this->getAccessTokenCacheKey());
        }
        
        if (empty($token)) {
            return false;
        }

        // 这里可以添加更复杂的令牌验证逻辑
        // 例如：检查令牌格式、过期时间等
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthUrl(string $redirectUri, string $state = '', array $scopes = []): string
    {
        $params = [
            'appid' => $this->config->get('app_key'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(',', $scopes ?: ['snsapi_login']),
            'state' => $state ?: uniqid(),
        ];

        $baseUrl = 'https://oapi.dingtalk.com/connect/oauth2/sns_authorize';
        
        return $baseUrl . '?' . http_build_query($params);
    }

    /**
     * {@inheritdoc}
     */
    public function getUserByCode(string $code): array
    {
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            return $this->getV2UserByCode($code);
        }
        
        return $this->getV1UserByCode($code);
    }

    /**
     * {@inheritdoc}
     */
    public function getJsApiSignature(string $url, array $jsApiList = []): array
    {
        $ticket = $this->getJsApiTicket();
        $nonceStr = uniqid();
        $timestamp = time();

        $string = "jsapi_ticket={$ticket}&noncestr={$nonceStr}&timestamp={$timestamp}&url={$url}";
        $signature = sha1($string);

        return [
            'agentId' => $this->config->get('agent_id'),
            'corpId' => $this->config->get('corp_id'),
            'timeStamp' => $timestamp,
            'nonceStr' => $nonceStr,
            'signature' => $signature,
            'jsApiList' => $jsApiList,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function verifySignature(array $data, string $signature): bool
    {
        $generatedSignature = $this->generateSignature($data);
        return hash_equals($generatedSignature, $signature);
    }

    /**
     * {@inheritdoc}
     */
    public function generateSignature(array $data): string
    {
        ksort($data);
        $string = '';
        
        foreach ($data as $key => $value) {
            if ($value !== '' && $key !== 'signature') {
                $string .= $key . '=' . $value . '&';
            }
        }
        
        $string = rtrim($string, '&');
        
        return sha1($string);
    }

    /**
     * {@inheritdoc}
     */
    public function setCredentials(string $appKey, string $appSecret): void
    {
        $this->config->set('app_key', $appKey);
        $this->config->set('app_secret', $appSecret);
    }

    /**
     * {@inheritdoc}
     */
    public function getCredentials(): array
    {
        return [
            'app_key' => $this->config->get('app_key'),
            'app_secret' => $this->config->get('app_secret'),
            'corp_id' => $this->config->get('corp_id'),
            'agent_id' => $this->config->get('agent_id'),
        ];
    }

    /**
     * 获取V1版本访问令牌
     */
    private function getV1AccessToken(): string
    {
        $url = $this->config->getApiBaseUrl() . '/gettoken';
        
        $response = $this->httpClient->get($url, [
            'appkey' => $this->config->get('app_key'),
            'appsecret' => $this->config->get('app_secret'),
        ]);

        if (!isset($response['access_token'])) {
            throw new AuthException(
                'Failed to get access token',
                'ACCESS_TOKEN_FAILED',
                $response
            );
        }

        $token = $response['access_token'];
        $expiresIn = $response['expires_in'] ?? 7200;
        
        // 缓存令牌，提前5分钟过期
        $this->cache->set(
            $this->getAccessTokenCacheKey(),
            $token,
            $expiresIn - 300
        );

        return $token;
    }

    /**
     * 获取V2版本访问令牌
     */
    private function getV2AccessToken(): string
    {
        $url = $this->config->getApiBaseUrl() . '/v1.0/oauth2/accessToken';
        
        $response = $this->httpClient->post($url, [
            'appKey' => $this->config->get('app_key'),
            'appSecret' => $this->config->get('app_secret'),
        ]);

        if (!isset($response['accessToken'])) {
            throw new AuthException(
                'Failed to get access token',
                'ACCESS_TOKEN_FAILED',
                $response
            );
        }

        $token = $response['accessToken'];
        $expiresIn = $response['expireIn'] ?? 7200;
        
        // 缓存令牌，提前5分钟过期
        $this->cache->set(
            $this->getAccessTokenCacheKey(),
            $token,
            $expiresIn - 300
        );

        return $token;
    }

    /**
     * 通过V1 API获取用户信息
     */
    private function getV1UserByCode(string $code): array
    {
        $accessToken = $this->getAccessToken();
        $url = $this->config->getApiBaseUrl() . '/user/getuserinfo';
        
        return $this->httpClient->get($url, [
            'access_token' => $accessToken,
            'code' => $code,
        ]);
    }

    /**
     * 通过V2 API获取用户信息
     */
    private function getV2UserByCode(string $code): array
    {
        $accessToken = $this->getAccessToken();
        $url = $this->config->getApiBaseUrl() . '/v1.0/oauth2/userAccessToken';
        
        $response = $this->httpClient->post($url, [
            'clientId' => $this->config->get('app_key'),
            'clientSecret' => $this->config->get('app_secret'),
            'code' => $code,
            'grantType' => 'authorization_code',
        ], [
            'x-acs-dingtalk-access-token' => $accessToken,
        ]);

        return $response;
    }

    /**
     * 获取JSAPI票据
     */
    private function getJsApiTicket(): string
    {
        $cacheKey = 'jsapi_ticket';
        
        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $accessToken = $this->getAccessToken();
        $url = $this->config->getApiBaseUrl() . '/get_jsapi_ticket';
        
        $response = $this->httpClient->get($url, [
            'access_token' => $accessToken,
        ]);

        if (!isset($response['ticket'])) {
            throw new AuthException(
                'Failed to get JSAPI ticket',
                'JSAPI_TICKET_FAILED',
                $response
            );
        }

        $ticket = $response['ticket'];
        $expiresIn = $response['expires_in'] ?? 7200;
        
        // 缓存票据，提前5分钟过期
        $this->cache->set($cacheKey, $ticket, $expiresIn - 300);

        return $ticket;
    }

    /**
     * 获取访问令牌缓存键
     */
    private function getAccessTokenCacheKey(): string
    {
        $appKey = $this->config->get('app_key');
        return self::ACCESS_TOKEN_CACHE_KEY . '_' . md5($appKey);
    }
}