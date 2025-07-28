<?php

declare(strict_types=1);

namespace DingTalk\Version;

use DingTalk\Contracts\ConfigInterface;
use DingTalk\Contracts\HttpClientInterface;
use DingTalk\Contracts\AuthInterface;
use DingTalk\Exceptions\ApiException;
use DingTalk\Exceptions\AuthException;
use DingTalk\Exceptions\NetworkException;
use DingTalk\Exceptions\RateLimitException;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * V1 API适配器
 * 
 * 专门处理钉钉旧版(V1) API的请求封装、认证流程、错误处理等
 */
class V1ApiAdapter
{
    /**
     * V1 API基础URL
     */
    public const V1_BASE_URL = 'https://oapi.dingtalk.com';

    /**
     * V1 API版本标识
     */
    public const API_VERSION = 'v1';

    /**
     * V1 认证方式
     */
    public const AUTH_TYPE_ACCESS_TOKEN = 'access_token';
    public const AUTH_TYPE_SIGNATURE = 'signature';

    /**
     * V1 错误码映射
     */
    private const V1_ERROR_CODES = [
        40001 => 'Invalid access token',
        40002 => 'Invalid app key',
        40003 => 'Invalid app secret',
        40004 => 'Invalid timestamp',
        40005 => 'Invalid signature',
        40006 => 'Invalid nonce',
        40007 => 'Invalid request format',
        40008 => 'Invalid parameter',
        40009 => 'Missing required parameter',
        40010 => 'Parameter value out of range',
        42001 => 'Access token expired',
        42002 => 'Refresh token expired',
        43001 => 'Request too frequent',
        43002 => 'API quota exceeded',
        50001 => 'Internal server error',
        50002 => 'Service unavailable',
        50003 => 'Database error',
        60001 => 'Permission denied',
        60002 => 'Insufficient privileges',
        60003 => 'Resource not found',
        60004 => 'Operation not allowed'
    ];

    /**
     * V1 参数格式映射
     */
    private const V1_PARAM_MAPPING = [
        // 用户相关
        'user.get' => [
            'user_id' => 'userid',
            'language' => 'lang'
        ],
        'user.list' => [
            'dept_id' => 'department_id',
            'include_child' => 'fetch_child'
        ],
        // 部门相关
        'department.list' => [
            'dept_id' => 'id',
            'include_child' => 'fetch_child'
        ],
        // 消息相关
        'message.send' => [
            'user_list' => 'touser',
            'dept_list' => 'toparty',
            'msg_type' => 'msgtype'
        ]
    ];

    /**
     * V1 响应格式映射
     */
    private const V1_RESPONSE_MAPPING = [
        'user.get' => [
            'userid' => 'user_id',
            'name' => 'username',
            'department' => 'dept_list'
        ],
        'user.list' => [
            'userlist' => 'user_list'
        ],
        'department.list' => [
            'department' => 'dept_list'
        ]
    ];

    /**
     * @var ConfigInterface 配置管理器
     */
    private ConfigInterface $config;

    /**
     * @var HttpClientInterface HTTP客户端
     */
    private HttpClientInterface $httpClient;

    /**
     * @var AuthInterface 认证管理器
     */
    private AuthInterface $auth;

    /**
     * @var LoggerInterface 日志记录器
     */
    private LoggerInterface $logger;

    /**
     * @var array 请求统计
     */
    private array $requestStats = [
        'total_requests' => 0,
        'successful_requests' => 0,
        'failed_requests' => 0,
        'rate_limited_requests' => 0,
        'auth_failed_requests' => 0
    ];

    /**
     * 构造函数
     * 
     * @param ConfigInterface $config 配置管理器
     * @param HttpClientInterface $httpClient HTTP客户端
     * @param AuthInterface $auth 认证管理器
     * @param LoggerInterface $logger 日志记录器
     */
    public function __construct(
        ConfigInterface $config,
        HttpClientInterface $httpClient,
        AuthInterface $auth,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->auth = $auth;
        $this->logger = $logger;
    }

    /**
     * 发送V1 API请求
     * 
     * @param string $method API方法名
     * @param array $params 请求参数
     * @param string $httpMethod HTTP方法 (GET|POST|PUT|DELETE)
     * @param array $options 请求选项
     * @return array 响应数据
     * @throws ApiException
     * @throws AuthException
     * @throws NetworkException
     * @throws RateLimitException
     */
    public function request(string $method, array $params = [], string $httpMethod = 'POST', array $options = []): array
    {
        $this->requestStats['total_requests']++;

        try {
            // 构建请求URL
            $url = $this->buildRequestUrl($method);
            
            // 转换请求参数格式
            $adaptedParams = $this->adaptRequestParams($method, $params);
            
            // 添加认证信息
            $authenticatedParams = $this->addAuthentication($adaptedParams, $method);
            
            // 发送HTTP请求
            $response = $this->sendHttpRequest($url, $authenticatedParams, $httpMethod, $options);
            
            // 处理V1响应格式
            $adaptedResponse = $this->adaptResponseData($method, $response);
            
            // 检查V1错误码
            $this->checkV1ErrorCode($adaptedResponse);

            $this->requestStats['successful_requests']++;
            
            $this->logger->info('V1 API request successful', [
                'method' => $method,
                'url' => $url,
                'http_method' => $httpMethod,
                'response_size' => strlen(json_encode($adaptedResponse))
            ]);

            return $adaptedResponse;

        } catch (RateLimitException $e) {
            $this->requestStats['rate_limited_requests']++;
            $this->requestStats['failed_requests']++;
            throw $e;
        } catch (AuthException $e) {
            $this->requestStats['auth_failed_requests']++;
            $this->requestStats['failed_requests']++;
            throw $e;
        } catch (\Exception $e) {
            $this->requestStats['failed_requests']++;
            
            $this->logger->error('V1 API request failed', [
                'method' => $method,
                'error' => $e->getMessage(),
                'params' => $params
            ]);

            throw new ApiException("V1 API request failed: " . $e->getMessage(), '', [], $e->getCode(), $e);
        }
    }

    /**
     * 转换请求参数格式
     * 
     * @param string $method API方法名
     * @param array $params 原始参数
     * @return array 转换后的参数
     */
    public function adaptRequestParams(string $method, array $params): array
    {
        $mapping = self::V1_PARAM_MAPPING[$method] ?? [];
        $adaptedParams = [];

        foreach ($params as $key => $value) {
            // 应用字段映射
            $mappedKey = $mapping[$key] ?? $key;
            $adaptedParams[$mappedKey] = $this->convertParamValue($value, $key, $method);
        }

        // 添加V1特有的参数
        $adaptedParams = $this->addV1SpecificParams($adaptedParams, $method);

        return $adaptedParams;
    }

    /**
     * 转换响应数据格式
     * 
     * @param string $method API方法名
     * @param array $response 原始响应
     * @return array 转换后的响应
     */
    public function adaptResponseData(string $method, array $response): array
    {
        $mapping = self::V1_RESPONSE_MAPPING[$method] ?? [];
        $adaptedResponse = [];

        foreach ($response as $key => $value) {
            // 应用字段映射
            $mappedKey = $mapping[$key] ?? $key;
            $adaptedResponse[$mappedKey] = $this->convertResponseValue($value, $key, $method);
        }

        return $adaptedResponse;
    }

    /**
     * 处理V1认证流程
     * 
     * @param array $params 请求参数
     * @param string $method API方法名
     * @return array 添加认证信息后的参数
     * @throws AuthException
     */
    public function addAuthentication(array $params, string $method): array
    {
        try {
            $authType = $this->getAuthType($method);

            switch ($authType) {
                case self::AUTH_TYPE_ACCESS_TOKEN:
                    return $this->addAccessTokenAuth($params);
                
                case self::AUTH_TYPE_SIGNATURE:
                    return $this->addSignatureAuth($params, $method);
                
                default:
                    throw new AuthException("Unsupported V1 auth type: {$authType}");
            }
        } catch (\Exception $e) {
            throw new AuthException("V1 authentication failed: " . $e->getMessage(), '', [], $e->getCode(), $e);
        }
    }

    /**
     * 处理V1错误处理
     * 
     * @param array $response 响应数据
     * @throws ApiException
     * @throws AuthException
     * @throws RateLimitException
     */
    public function checkV1ErrorCode(array $response): void
    {
        if (!isset($response['errcode']) || $response['errcode'] === 0) {
            return;
        }

        $errorCode = (int) $response['errcode'];
        $errorMessage = $response['errmsg'] ?? 'Unknown error';
        $detailedMessage = self::V1_ERROR_CODES[$errorCode] ?? $errorMessage;

        // 根据错误码类型抛出相应异常
        if ($errorCode >= 40001 && $errorCode <= 42002) {
            throw new AuthException("V1 Auth Error [{$errorCode}]: {$detailedMessage}");
        }

        if ($errorCode >= 43001 && $errorCode <= 43002) {
            throw new RateLimitException("V1 Rate Limit Error [{$errorCode}]: {$detailedMessage}");
        }

        throw new ApiException("V1 API Error [{$errorCode}]: {$detailedMessage}");
    }

    /**
     * 处理V1限流处理
     * 
     * @param array $response 响应数据
     * @return bool 是否需要重试
     */
    public function handleV1RateLimit(array $response): bool
    {
        if (!isset($response['errcode'])) {
            return false;
        }

        $errorCode = (int) $response['errcode'];
        
        // V1 API限流错误码
        if ($errorCode === 43001 || $errorCode === 43002) {
            $retryAfter = $this->getRetryAfterFromResponse($response);
            
            $this->logger->warning('V1 API rate limited', [
                'error_code' => $errorCode,
                'retry_after' => $retryAfter
            ]);

            // 等待后重试
            if ($retryAfter > 0 && $retryAfter <= 60) {
                sleep($retryAfter);
                return true;
            }
        }

        return false;
    }

    /**
     * 获取请求统计信息
     * 
     * @return array 统计信息
     */
    public function getRequestStats(): array
    {
        return $this->requestStats;
    }

    /**
     * 清除请求统计信息
     * 
     * @return void
     */
    public function clearRequestStats(): void
    {
        $this->requestStats = [
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'rate_limited_requests' => 0,
            'auth_failed_requests' => 0
        ];
    }

    /**
     * 构建请求URL
     * 
     * @param string $method API方法名
     * @return string 完整的请求URL
     */
    private function buildRequestUrl(string $method): string
    {
        $baseUrl = $this->config->get('v1_api_base_url', self::V1_BASE_URL);
        
        // 将方法名转换为V1 API路径
        $path = $this->convertMethodToPath($method);
        
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    /**
     * 将方法名转换为V1 API路径
     * 
     * @param string $method API方法名
     * @return string API路径
     */
    private function convertMethodToPath(string $method): string
    {
        // V1 API路径映射
        $pathMapping = [
            'user.get' => 'user/get',
            'user.list' => 'user/list',
            'user.create' => 'user/create',
            'user.update' => 'user/update',
            'user.delete' => 'user/delete',
            'department.list' => 'department/list',
            'department.get' => 'department/get',
            'department.create' => 'department/create',
            'department.update' => 'department/update',
            'department.delete' => 'department/delete',
            'message.send' => 'message/send',
            'message.send_to_conversation' => 'message/send_to_conversation',
            'media.upload' => 'media/upload',
            'media.download' => 'media/download',
            'attendance.list' => 'attendance/list'
        ];

        return $pathMapping[$method] ?? str_replace('.', '/', $method);
    }

    /**
     * 发送HTTP请求
     * 
     * @param string $url 请求URL
     * @param array $params 请求参数
     * @param string $httpMethod HTTP方法
     * @param array $options 请求选项
     * @return array 响应数据
     * @throws NetworkException
     */
    private function sendHttpRequest(string $url, array $params, string $httpMethod, array $options): array
    {
        try {
            $requestOptions = array_merge([
                'timeout' => $this->config->get('v1_api_timeout', 30),
                'headers' => [
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'DingTalk-SDK-PHP-V1/' . $this->getSDKVersion()
                ]
            ], $options);

            switch (strtoupper($httpMethod)) {
                case 'GET':
                    $response = $this->httpClient->get($url, $params, $requestOptions);
                    break;
                
                case 'POST':
                    $response = $this->httpClient->post($url, $params, $requestOptions);
                    break;
                
                case 'PUT':
                    $response = $this->httpClient->put($url, $params, $requestOptions);
                    break;
                
                case 'DELETE':
                    $response = $this->httpClient->delete($url, $params, $requestOptions);
                    break;
                
                default:
                    throw new InvalidArgumentException("Unsupported HTTP method: {$httpMethod}");
            }

            return $response;

        } catch (\Exception $e) {
            throw new NetworkException("V1 API network request failed: " . $e->getMessage(), '', [], $e->getCode(), $e);
        }
    }

    /**
     * 转换参数值
     * 
     * @param mixed $value 原始值
     * @param string $key 参数键
     * @param string $method API方法名
     * @return mixed 转换后的值
     */
    private function convertParamValue($value, string $key, string $method)
    {
        // V1 API特殊参数处理
        switch ($key) {
            case 'timestamp':
                return is_numeric($value) ? (int) $value : time();
            
            case 'nonce':
                return is_string($value) ? $value : (string) $value;
            
            case 'fetch_child':
            case 'include_child':
                return $value ? 'true' : 'false';
            
            default:
                return $value;
        }
    }

    /**
     * 转换响应值
     * 
     * @param mixed $value 原始值
     * @param string $key 响应键
     * @param string $method API方法名
     * @return mixed 转换后的值
     */
    private function convertResponseValue($value, string $key, string $method)
    {
        // V1 API特殊响应处理
        switch ($key) {
            case 'errcode':
                return (int) $value;
            
            case 'errmsg':
                return (string) $value;
            
            default:
                return $value;
        }
    }

    /**
     * 添加V1特有参数
     * 
     * @param array $params 参数数组
     * @param string $method API方法名
     * @return array 添加特有参数后的数组
     */
    private function addV1SpecificParams(array $params, string $method): array
    {
        // 添加时间戳
        if (!isset($params['timestamp'])) {
            $params['timestamp'] = time();
        }

        // 添加随机数
        if (!isset($params['nonce'])) {
            $params['nonce'] = uniqid();
        }

        return $params;
    }

    /**
     * 获取认证类型
     * 
     * @param string $method API方法名
     * @return string 认证类型
     */
    private function getAuthType(string $method): string
    {
        // 根据方法名确定认证类型
        $signatureMethods = [
            'message.send',
            'message.send_to_conversation',
            'media.upload'
        ];

        return in_array($method, $signatureMethods) 
            ? self::AUTH_TYPE_SIGNATURE 
            : self::AUTH_TYPE_ACCESS_TOKEN;
    }

    /**
     * 添加访问令牌认证
     * 
     * @param array $params 参数数组
     * @return array 添加认证信息后的参数
     * @throws AuthException
     */
    private function addAccessTokenAuth(array $params): array
    {
        $accessToken = $this->auth->getAccessToken();
        
        if (empty($accessToken)) {
            throw new AuthException('Access token is required for V1 API');
        }

        $params['access_token'] = $accessToken;
        return $params;
    }

    /**
     * 添加签名认证
     * 
     * @param array $params 参数数组
     * @param string $method API方法名
     * @return array 添加认证信息后的参数
     * @throws AuthException
     */
    private function addSignatureAuth(array $params, string $method): array
    {
        $appKey = $this->config->get('app_key');
        $appSecret = $this->config->get('app_secret');
        
        if (empty($appKey) || empty($appSecret)) {
            throw new AuthException('App key and secret are required for V1 signature auth');
        }

        $timestamp = $params['timestamp'] ?? time();
        $nonce = $params['nonce'] ?? uniqid();

        // 生成V1签名
        $signature = $this->generateV1Signature($appSecret, $timestamp, $nonce);

        $params['app_key'] = $appKey;
        $params['timestamp'] = $timestamp;
        $params['nonce'] = $nonce;
        $params['signature'] = $signature;

        return $params;
    }

    /**
     * 生成V1签名
     * 
     * @param string $appSecret 应用密钥
     * @param int $timestamp 时间戳
     * @param string $nonce 随机数
     * @return string 签名
     */
    private function generateV1Signature(string $appSecret, int $timestamp, string $nonce): string
    {
        $stringToSign = $timestamp . "\n" . $nonce;
        return base64_encode(hash_hmac('sha256', $stringToSign, $appSecret, true));
    }

    /**
     * 从响应中获取重试等待时间
     * 
     * @param array $response 响应数据
     * @return int 等待时间（秒）
     */
    private function getRetryAfterFromResponse(array $response): int
    {
        // V1 API通常在响应中包含retry_after字段
        if (isset($response['retry_after'])) {
            return (int) $response['retry_after'];
        }

        // 默认等待时间
        $retryDelay = $this->config->get('v1_api_retry_delay', 5);
        return is_int($retryDelay) ? $retryDelay : 5;
    }

    /**
     * 获取SDK版本
     * 
     * @return string SDK版本
     */
    private function getSDKVersion(): string
    {
        return $this->config->get('sdk_version', '1.0.0');
    }
}