<?php

declare(strict_types=1);

namespace DingTalk\Auth\Strategies;

use DingTalk\Exceptions\AuthException;

/**
 * 第三方个人应用认证策略
 * 
 * 适用于第三方开发的个人应用，通过个人授权流程获取用户信息
 */
class ThirdPartyPersonalAuthStrategy extends AbstractAuthStrategy
{
    /**
     * 策略类型
     */
    const STRATEGY_TYPE = 'third_party_personal';

    /**
     * 用户访问令牌
     */
    private ?string $userAccessToken = null;

    /**
     * 用户刷新令牌
     */
    private ?string $userRefreshToken = null;

    /**
     * 刷新访问令牌
     */
    public function refreshAccessToken(): string
    {
        $this->log('info', 'Refreshing third party personal access token', [
            'app_key' => $this->appKey,
        ]);

        // 第三方个人应用需要用户授权，不能直接获取access_token
        // 这里返回应用级别的access_token用于基础API调用
        $params = [
            'appkey' => $this->appKey,
            'appsecret' => $this->appSecret,
        ];

        $url = $this->getApiBaseUrl() . '/gettoken';
        $response = $this->sendRequest('GET', $url, $params);

        if (!isset($response['access_token'])) {
            $errorMsg = $response['errmsg'] ?? 'Unknown error';
            $errorCode = $response['errcode'] ?? -1;
            
            throw new AuthException("Failed to get access token: {$errorMsg}", $errorCode);
        }

        $token = $response['access_token'];
        $expiresIn = $response['expires_in'] ?? 7200;
        
        $this->cacheToken($token, $expiresIn);

        $this->log('info', 'Third party personal access token refreshed successfully');

        return $token;
    }

    /**
     * 获取用户授权URL
     */
    public function getAuthUrl(string $redirectUri, string $state = '', array $scopes = []): string
    {
        // 第三方个人应用授权URL
        $defaultScopes = ['openid', 'corpid'];
        $scopes = array_merge($defaultScopes, $scopes);

        $params = [
            'appid' => $this->appKey,
            'redirect_uri' => urlencode($redirectUri),
            'response_type' => 'code',
            'scope' => implode(',', $scopes),
            'state' => $state ?: uniqid(),
            'prompt' => 'consent',
        ];

        $queryString = http_build_query($params);
        return $this->getApiBaseUrl() . '/connect/oauth2/sns_authorize?' . $queryString;
    }

    /**
     * 通过授权码获取用户信息
     */
    public function getUserByCode(string $code): array
    {
        $this->log('info', 'Getting user info by code for third party personal', [
            'code' => substr($code, 0, 10) . '...',
        ]);

        // 先通过code获取用户access_token
        $tokenInfo = $this->getUserAccessToken($code);
        
        // 使用用户access_token获取用户信息
        $userInfo = $this->getUserInfo($tokenInfo['access_token']);

        $this->log('info', 'User info retrieved successfully for third party personal', [
            'openid' => $userInfo['openid'] ?? '',
        ]);

        return array_merge($tokenInfo, $userInfo);
    }

    /**
     * 获取JSAPI签名
     */
    public function getJsApiSignature(string $url, array $jsApiList = []): array
    {
        $this->log('info', 'Generating JSAPI signature for third party personal', [
            'url' => $url,
        ]);

        // 第三方个人应用的JSAPI签名
        $ticket = $this->getJsApiTicket();
        
        $timestamp = time();
        $nonceStr = uniqid();
        
        $signString = "jsapi_ticket={$ticket}&noncestr={$nonceStr}&timestamp={$timestamp}&url={$url}";
        $signature = sha1($signString);

        $result = [
            'appId' => $this->appKey,
            'timeStamp' => $timestamp,
            'nonceStr' => $nonceStr,
            'signature' => $signature,
            'jsApiList' => $jsApiList ?: ['runtime.info', 'biz.contact.complexPicker'],
        ];

        $this->log('info', 'JSAPI signature generated successfully for third party personal');

        return $result;
    }

    /**
     * 获取策略类型
     */
    public function getStrategyType(): string
    {
        return self::STRATEGY_TYPE;
    }

    /**
     * 获取支持的认证方式
     */
    public function getSupportedAuthMethods(): array
    {
        return [
            'oauth2',
            'user_access_token',
            'jsapi',
            'openapi',
        ];
    }

    /**
     * 处理免登认证
     */
    public function handleSsoAuth(string $authCode): array
    {
        $this->log('info', 'Handling SSO authentication for third party personal', [
            'auth_code' => substr($authCode, 0, 10) . '...',
        ]);

        // 第三方个人应用的免登认证
        $userInfo = $this->getUserByCode($authCode);

        $this->log('info', 'SSO authentication successful for third party personal');

        return $userInfo;
    }

    /**
     * 获取OAuth2.0配置
     */
    public function getOAuth2Config(): array
    {
        return [
            'client_id' => $this->appKey,
            'client_secret' => $this->appSecret,
            'authorize_url' => $this->getApiBaseUrl() . '/connect/oauth2/sns_authorize',
            'token_url' => $this->getApiBaseUrl() . '/sns/gettoken',
            'user_info_url' => $this->getApiBaseUrl() . '/sns/getuserinfo',
            'refresh_token_url' => $this->getApiBaseUrl() . '/sns/get_sns_token',
            'scope' => 'openid,corpid',
            'response_type' => 'code',
            'grant_type' => 'authorization_code',
        ];
    }

    /**
     * 刷新用户访问令牌
     */
    public function refreshUserAccessToken(): array
    {
        if (!$this->userRefreshToken) {
            throw new AuthException('User refresh token is required');
        }

        $this->log('info', 'Refreshing user access token');

        $params = [
            'appid' => $this->appKey,
            'appsecret' => $this->appSecret,
            'refresh_token' => $this->userRefreshToken,
            'grant_type' => 'refresh_token',
        ];

        $url = $this->getApiBaseUrl() . '/sns/get_sns_token';
        $response = $this->sendRequest('POST', $url, $params);

        if (!isset($response['access_token'])) {
            throw new AuthException('Failed to refresh user access token');
        }

        $this->userAccessToken = $response['access_token'];
        $this->userRefreshToken = $response['refresh_token'];

        $this->log('info', 'User access token refreshed successfully');

        return $response;
    }

    /**
     * 设置用户令牌
     */
    public function setUserTokens(string $accessToken, string $refreshToken): void
    {
        $this->userAccessToken = $accessToken;
        $this->userRefreshToken = $refreshToken;
    }

    /**
     * 获取用户访问令牌
     */
    private function getUserAccessToken(string $code): array
    {
        $timestamp = time() * 1000;
        $signature = $this->generateSnsSignature($timestamp);

        $url = $this->getApiBaseUrl() . '/sns/gettoken';
        $params = [
            'appid' => $this->appKey,
            'appsecret' => $this->appSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'timestamp' => $timestamp,
            'signature' => $signature,
        ];

        $response = $this->sendRequest('POST', $url, $params);

        if (!isset($response['access_token'])) {
            throw new AuthException('Failed to get user access token');
        }

        $this->userAccessToken = $response['access_token'];
        $this->userRefreshToken = $response['refresh_token'] ?? '';

        return $response;
    }

    /**
     * 获取用户信息
     */
    private function getUserInfo(string $userAccessToken): array
    {
        $url = $this->getApiBaseUrl() . '/sns/getuserinfo';
        $params = [
            'sns_token' => $userAccessToken,
        ];

        $response = $this->sendRequest('GET', $url, $params);

        if (isset($response['errcode']) && $response['errcode'] !== 0) {
            throw new AuthException('Failed to get user info');
        }

        return $response;
    }

    /**
     * 获取JSAPI票据
     */
    private function getJsApiTicket(): string
    {
        $cacheKey = "dingtalk:personal_jsapi_ticket:{$this->appKey}";
        $cachedTicket = $this->cache->get($cacheKey);

        if ($cachedTicket) {
            return $cachedTicket;
        }

        $accessToken = $this->getAccessToken();
        
        $url = $this->getApiBaseUrl() . '/get_jsapi_ticket';
        $params = [
            'access_token' => $accessToken,
            'type' => 'jsapi',
        ];

        $response = $this->sendRequest('GET', $url, $params);

        if (!isset($response['ticket'])) {
            throw new AuthException('Failed to get jsapi ticket');
        }

        $ticket = $response['ticket'];
        $expiresIn = $response['expires_in'] ?? 7200;
        
        $this->cache->set($cacheKey, $ticket, $expiresIn);

        return $ticket;
    }

    /**
     * 生成SNS签名
     */
    private function generateSnsSignature(int $timestamp): string
    {
        $signString = $timestamp . "\n" . $this->appSecret;
        return base64_encode(hash_hmac('sha256', $signString, $this->appSecret, true));
    }

    /**
     * 获取用户详细信息
     */
    public function getUserProfile(string $userAccessToken): array
    {
        $this->log('info', 'Getting user profile');

        $url = $this->getApiBaseUrl() . '/sns/getuserinfo_bycode';
        $params = [
            'sns_token' => $userAccessToken,
        ];

        $response = $this->sendRequest('GET', $url, $params);

        if (isset($response['errcode']) && $response['errcode'] !== 0) {
            throw new AuthException('Failed to get user profile');
        }

        return $response;
    }

    /**
     * 验证用户访问令牌
     */
    public function validateUserAccessToken(string $userAccessToken): bool
    {
        try {
            $this->getUserInfo($userAccessToken);
            return true;
        } catch (AuthException $e) {
            return false;
        }
    }
}