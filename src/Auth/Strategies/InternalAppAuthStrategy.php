<?php

declare(strict_types=1);

namespace DingTalk\Auth\Strategies;

use DingTalk\Exceptions\AuthException;

/**
 * 企业内部应用认证策略
 * 
 * 适用于企业内部开发的应用，使用企业的AppKey和AppSecret进行认证
 */
class InternalAppAuthStrategy extends AbstractAuthStrategy
{
    /**
     * 策略类型
     */
    const STRATEGY_TYPE = 'internal_app';

    /**
     * 刷新访问令牌
     */
    public function refreshAccessToken(): string
    {
        $this->log('info', 'Refreshing internal app access token', [
            'app_key' => $this->appKey,
        ]);

        // 构建请求参数
        $params = [
            'appkey' => $this->appKey,
            'appsecret' => $this->appSecret,
        ];

        // 发送请求获取令牌
        $url = $this->getApiBaseUrl() . '/gettoken';
        $response = $this->sendRequest('GET', $url, $params);

        // 检查响应
        if (!isset($response['access_token'])) {
            $errorMsg = $response['errmsg'] ?? 'Unknown error';
            $errorCode = $response['errcode'] ?? -1;
            
            $this->log('error', 'Failed to get access token', [
                'error_code' => $errorCode,
                'error_msg' => $errorMsg,
            ]);
            
            throw new AuthException("Failed to get access token: {$errorMsg}", $errorCode);
        }

        // 缓存令牌
        $token = $response['access_token'];
        $expiresIn = $response['expires_in'] ?? 7200; // 默认2小时
        
        $this->cacheToken($token, $expiresIn);

        $this->log('info', 'Access token refreshed successfully', [
            'expires_in' => $expiresIn,
        ]);

        return $token;
    }

    /**
     * 获取用户授权URL
     */
    public function getAuthUrl(string $redirectUri, string $state = '', array $scopes = []): string
    {
        // 企业内部应用通常不需要用户授权，直接使用企业授权
        $params = [
            'appid' => $this->appKey,
            'redirect_uri' => urlencode($redirectUri),
            'response_type' => 'code',
            'scope' => 'snsapi_login',
            'state' => $state ?: uniqid(),
        ];

        $queryString = http_build_query($params);
        return $this->getApiBaseUrl() . '/connect/oauth2/sns_authorize?' . $queryString;
    }

    /**
     * 通过授权码获取用户信息
     */
    public function getUserByCode(string $code): array
    {
        $this->log('info', 'Getting user info by code', [
            'code' => substr($code, 0, 10) . '...',
        ]);

        // 先获取用户的unionid
        $unionId = $this->getUserUnionId($code);
        
        // 再通过unionid获取用户详细信息
        $userInfo = $this->getUserInfoByUnionId($unionId);

        $this->log('info', 'User info retrieved successfully', [
            'union_id' => $unionId,
            'user_id' => $userInfo['userid'] ?? '',
        ]);

        return $userInfo;
    }

    /**
     * 获取JSAPI签名
     */
    public function getJsApiSignature(string $url, array $jsApiList = []): array
    {
        $this->log('info', 'Generating JSAPI signature', [
            'url' => $url,
            'js_api_list' => $jsApiList,
        ]);

        // 获取jsapi_ticket
        $ticket = $this->getJsApiTicket();
        
        // 生成签名参数
        $timestamp = time();
        $nonceStr = uniqid();
        
        // 构建签名字符串
        $signString = "jsapi_ticket={$ticket}&noncestr={$nonceStr}&timestamp={$timestamp}&url={$url}";
        $signature = sha1($signString);

        $result = [
            'agentId' => $this->config->get('agent_id', ''),
            'corpId' => $this->config->get('corp_id', ''),
            'timeStamp' => $timestamp,
            'nonceStr' => $nonceStr,
            'signature' => $signature,
            'jsApiList' => $jsApiList ?: ['device.notification.confirm', 'biz.contact.choose'],
        ];

        $this->log('info', 'JSAPI signature generated successfully');

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
            'access_token',
            'jsapi',
            'sso',
            'oauth2',
        ];
    }

    /**
     * 处理免登认证
     */
    public function handleSsoAuth(string $authCode): array
    {
        $this->log('info', 'Handling SSO authentication', [
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

        $this->log('info', 'SSO authentication successful', [
            'user_id' => $response['userid'] ?? '',
        ]);

        return $response;
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
            'scope' => 'snsapi_login',
            'response_type' => 'code',
        ];
    }

    /**
     * 获取用户UnionId
     */
    private function getUserUnionId(string $code): string
    {
        $url = $this->getApiBaseUrl() . '/sns/getuserinfo_bycode';
        $params = [
            'accessKey' => $this->appKey,
            'timestamp' => time() * 1000,
            'signature' => $this->generateSnsSignature($code),
        ];

        $response = $this->sendRequest('POST', $url, [
            'tmp_auth_code' => $code,
        ], [
            'Content-Type' => 'application/json',
        ]);

        if (!isset($response['user_info']['unionid'])) {
            throw new AuthException('Failed to get user unionid');
        }

        return $response['user_info']['unionid'];
    }

    /**
     * 通过UnionId获取用户信息
     */
    private function getUserInfoByUnionId(string $unionId): array
    {
        $accessToken = $this->getAccessToken();
        
        $url = $this->getApiBaseUrl() . '/user/getUseridByUnionid';
        $params = [
            'access_token' => $accessToken,
            'unionid' => $unionId,
        ];

        $response = $this->sendRequest('GET', $url, $params);

        if (isset($response['errcode']) && $response['errcode'] !== 0) {
            throw new AuthException('Failed to get user info by unionid');
        }

        return $response;
    }

    /**
     * 获取JSAPI票据
     */
    private function getJsApiTicket(): string
    {
        $cacheKey = "dingtalk:jsapi_ticket:{$this->appKey}";
        $cachedTicket = $this->cache->get($cacheKey);

        if ($cachedTicket) {
            return $cachedTicket;
        }

        $accessToken = $this->getAccessToken();
        
        $url = $this->getApiBaseUrl() . '/get_jsapi_ticket';
        $params = [
            'access_token' => $accessToken,
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
    private function generateSnsSignature(string $code): string
    {
        $timestamp = time() * 1000;
        $signString = $timestamp . "\n" . $code;
        
        return base64_encode(hash_hmac('sha256', $signString, $this->appSecret, true));
    }
}