<?php

declare(strict_types=1);

namespace DingTalk\Auth\Strategies;

use DingTalk\Exceptions\AuthException;

/**
 * 第三方企业应用认证策略
 * 
 * 适用于第三方开发的企业级应用，需要通过企业授权流程
 */
class ThirdPartyEnterpriseAuthStrategy extends AbstractAuthStrategy
{
    /**
     * 策略类型
     */
    const STRATEGY_TYPE = 'third_party_enterprise';

    /**
     * 企业授权码
     */
    private ?string $authCorpId = null;

    /**
     * 永久授权码
     */
    private ?string $permanentCode = null;

    /**
     * 刷新访问令牌
     */
    public function refreshAccessToken(): string
    {
        $this->log('info', 'Refreshing third party enterprise access token', [
            'app_key' => $this->appKey,
            'corp_id' => $this->authCorpId,
        ]);

        if (!$this->permanentCode) {
            throw new AuthException('Permanent code is required for third party enterprise auth');
        }

        // 构建请求参数
        $params = [
            'suite_key' => $this->appKey,
            'suite_secret' => $this->appSecret,
            'suite_ticket' => $this->getSuiteTicket(),
        ];

        // 先获取suite_access_token
        $suiteAccessToken = $this->getSuiteAccessToken($params);

        // 使用suite_access_token和permanent_code获取企业access_token
        $corpAccessToken = $this->getCorpAccessToken($suiteAccessToken);

        // 缓存令牌
        $expiresIn = 7200; // 2小时
        $this->cacheToken($corpAccessToken, $expiresIn);

        $this->log('info', 'Third party enterprise access token refreshed successfully');

        return $corpAccessToken;
    }

    /**
     * 获取用户授权URL
     */
    public function getAuthUrl(string $redirectUri, string $state = '', array $scopes = []): string
    {
        // 第三方企业应用授权URL
        $params = [
            'suite_id' => $this->appKey,
            'pre_auth_code' => $this->getPreAuthCode(),
            'redirect_uri' => urlencode($redirectUri),
            'state' => $state ?: uniqid(),
        ];

        $queryString = http_build_query($params);
        return $this->getApiBaseUrl() . '/connect/oauth2/authorize?' . $queryString;
    }

    /**
     * 通过授权码获取用户信息
     */
    public function getUserByCode(string $code): array
    {
        $this->log('info', 'Getting user info by code for third party enterprise', [
            'code' => substr($code, 0, 10) . '...',
        ]);

        // 通过临时授权码获取永久授权码
        if (!$this->permanentCode) {
            $this->exchangePermanentCode($code);
        }

        // 获取访问令牌
        $accessToken = $this->getAccessToken();

        // 通过code获取用户信息
        $url = $this->getApiBaseUrl() . '/user/getuserinfo';
        $params = [
            'access_token' => $accessToken,
            'code' => $code,
        ];

        $response = $this->sendRequest('GET', $url, $params);

        if (isset($response['errcode']) && $response['errcode'] !== 0) {
            $errorMsg = $response['errmsg'] ?? 'Unknown error';
            throw new AuthException("Failed to get user info: {$errorMsg}", $response['errcode']);
        }

        $this->log('info', 'User info retrieved successfully for third party enterprise');

        return $response;
    }

    /**
     * 获取JSAPI签名
     */
    public function getJsApiSignature(string $url, array $jsApiList = []): array
    {
        $this->log('info', 'Generating JSAPI signature for third party enterprise', [
            'url' => $url,
        ]);

        // 获取企业jsapi_ticket
        $ticket = $this->getCorpJsApiTicket();
        
        // 生成签名参数
        $timestamp = time();
        $nonceStr = uniqid();
        
        // 构建签名字符串
        $signString = "jsapi_ticket={$ticket}&noncestr={$nonceStr}&timestamp={$timestamp}&url={$url}";
        $signature = sha1($signString);

        $result = [
            'agentId' => $this->config->get('agent_id', ''),
            'corpId' => $this->authCorpId,
            'timeStamp' => $timestamp,
            'nonceStr' => $nonceStr,
            'signature' => $signature,
            'jsApiList' => $jsApiList ?: ['device.notification.confirm', 'biz.contact.choose'],
        ];

        $this->log('info', 'JSAPI signature generated successfully for third party enterprise');

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
            'suite_access_token',
            'corp_access_token',
            'jsapi',
            'oauth2',
            'pre_auth',
        ];
    }

    /**
     * 处理免登认证
     */
    public function handleSsoAuth(string $authCode): array
    {
        $this->log('info', 'Handling SSO authentication for third party enterprise', [
            'auth_code' => substr($authCode, 0, 10) . '...',
        ]);

        $accessToken = $this->getAccessToken();
        
        // 通过免登授权码获取用户信息
        $url = $this->getApiBaseUrl() . '/user/getuserinfo';
        $params = [
            'access_token' => $accessToken,
            'code' => $authCode,
        ];

        $response = $this->sendRequest('GET', $url, $params);

        if (isset($response['errcode']) && $response['errcode'] !== 0) {
            $errorMsg = $response['errmsg'] ?? 'Unknown error';
            throw new AuthException("SSO auth failed: {$errorMsg}", $response['errcode']);
        }

        $this->log('info', 'SSO authentication successful for third party enterprise');

        return $response;
    }

    /**
     * 获取OAuth2.0配置
     */
    public function getOAuth2Config(): array
    {
        return [
            'suite_id' => $this->appKey,
            'suite_secret' => $this->appSecret,
            'authorize_url' => $this->getApiBaseUrl() . '/connect/oauth2/authorize',
            'token_url' => $this->getApiBaseUrl() . '/service/get_corp_token',
            'pre_auth_url' => $this->getApiBaseUrl() . '/service/get_pre_auth_code',
            'permanent_code_url' => $this->getApiBaseUrl() . '/service/get_permanent_code',
            'scope' => 'snsapi_base',
            'response_type' => 'code',
        ];
    }

    /**
     * 设置企业授权信息
     */
    public function setCorpAuth(string $corpId, string $permanentCode): void
    {
        $this->authCorpId = $corpId;
        $this->permanentCode = $permanentCode;
    }

    /**
     * 获取套件访问令牌
     */
    private function getSuiteAccessToken(array $params): string
    {
        $cacheKey = "dingtalk:suite_token:{$this->appKey}";
        $cachedToken = $this->cache->get($cacheKey);

        if ($cachedToken) {
            return $cachedToken;
        }

        $url = $this->getApiBaseUrl() . '/service/get_suite_token';
        $response = $this->sendRequest('POST', $url, $params);

        if (!isset($response['suite_access_token'])) {
            throw new AuthException('Failed to get suite access token');
        }

        $token = $response['suite_access_token'];
        $expiresIn = $response['expires_in'] ?? 7200;
        
        $this->cache->set($cacheKey, $token, $expiresIn);

        return $token;
    }

    /**
     * 获取企业访问令牌
     */
    private function getCorpAccessToken(string $suiteAccessToken): string
    {
        $url = $this->getApiBaseUrl() . '/service/get_corp_token';
        $params = [
            'suite_access_token' => $suiteAccessToken,
            'auth_corpid' => $this->authCorpId,
            'permanent_code' => $this->permanentCode,
        ];

        $response = $this->sendRequest('POST', $url, $params);

        if (!isset($response['access_token'])) {
            throw new AuthException('Failed to get corp access token');
        }

        return $response['access_token'];
    }

    /**
     * 获取预授权码
     */
    private function getPreAuthCode(): string
    {
        $suiteAccessToken = $this->getSuiteAccessToken([
            'suite_key' => $this->appKey,
            'suite_secret' => $this->appSecret,
            'suite_ticket' => $this->getSuiteTicket(),
        ]);

        $url = $this->getApiBaseUrl() . '/service/get_pre_auth_code';
        $params = [
            'suite_access_token' => $suiteAccessToken,
        ];

        $response = $this->sendRequest('GET', $url, $params);

        if (!isset($response['pre_auth_code'])) {
            throw new AuthException('Failed to get pre auth code');
        }

        return $response['pre_auth_code'];
    }

    /**
     * 交换永久授权码
     */
    private function exchangePermanentCode(string $tmpAuthCode): void
    {
        $suiteAccessToken = $this->getSuiteAccessToken([
            'suite_key' => $this->appKey,
            'suite_secret' => $this->appSecret,
            'suite_ticket' => $this->getSuiteTicket(),
        ]);

        $url = $this->getApiBaseUrl() . '/service/get_permanent_code';
        $params = [
            'suite_access_token' => $suiteAccessToken,
            'tmp_auth_code' => $tmpAuthCode,
        ];

        $response = $this->sendRequest('POST', $url, $params);

        if (!isset($response['permanent_code'])) {
            throw new AuthException('Failed to exchange permanent code');
        }

        $this->permanentCode = $response['permanent_code'];
        $this->authCorpId = $response['auth_corp_info']['corpid'];
    }

    /**
     * 获取套件票据
     */
    private function getSuiteTicket(): string
    {
        // 套件票据通常通过钉钉推送获得，这里从缓存中获取
        $cacheKey = "dingtalk:suite_ticket:{$this->appKey}";
        $ticket = $this->cache->get($cacheKey);

        if (!$ticket) {
            throw new AuthException('Suite ticket not found, please check push callback');
        }

        return $ticket;
    }

    /**
     * 获取企业JSAPI票据
     */
    private function getCorpJsApiTicket(): string
    {
        $cacheKey = "dingtalk:corp_jsapi_ticket:{$this->authCorpId}";
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
            throw new AuthException('Failed to get corp jsapi ticket');
        }

        $ticket = $response['ticket'];
        $expiresIn = $response['expires_in'] ?? 7200;
        
        $this->cache->set($cacheKey, $ticket, $expiresIn);

        return $ticket;
    }
}