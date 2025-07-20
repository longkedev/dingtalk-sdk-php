<?php

declare(strict_types=1);

namespace DingTalk\Services;

use DingTalk\Contracts\ConfigInterface;
use DingTalk\Contracts\HttpClientInterface;
use DingTalk\Contracts\AuthInterface;
use DingTalk\Contracts\CacheInterface;
use Psr\Log\LoggerInterface;

/**
 * 应用服务基类
 * 
 * 为所有应用服务提供通用功能
 */
abstract class BaseService
{
    /**
     * 配置管理器
     */
    protected ConfigInterface $config;

    /**
     * HTTP客户端
     */
    protected HttpClientInterface $httpClient;

    /**
     * 认证管理器
     */
    protected AuthInterface $auth;

    /**
     * 缓存管理器
     */
    protected CacheInterface $cache;

    /**
     * 日志管理器
     */
    protected LoggerInterface $logger;

    /**
     * 构造函数
     */
    public function __construct(
        ConfigInterface $config,
        HttpClientInterface $httpClient,
        AuthInterface $auth,
        CacheInterface $cache,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->auth = $auth;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * 发送GET请求
     */
    protected function get(string $endpoint, array $params = [], array $headers = []): array
    {
        $url = $this->buildUrl($endpoint);
        $headers = $this->buildHeaders($headers);
        
        $this->logger->logApiRequest('GET', $url, $params, $headers);
        
        $startTime = microtime(true);
        $response = $this->httpClient->get($url, $params, $headers);
        $duration = microtime(true) - $startTime;
        
        $this->logger->logApiResponse(200, $response, $duration);
        
        return $this->handleResponse($response);
    }

    /**
     * 发送POST请求
     */
    protected function post(string $endpoint, array $data = [], array $headers = []): array
    {
        $url = $this->buildUrl($endpoint);
        $headers = $this->buildHeaders($headers);
        
        $this->logger->logApiRequest('POST', $url, $data, $headers);
        
        $startTime = microtime(true);
        $response = $this->httpClient->post($url, $data, $headers);
        $duration = microtime(true) - $startTime;
        
        $this->logger->logApiResponse(200, $response, $duration);
        
        return $this->handleResponse($response);
    }

    /**
     * 发送PUT请求
     */
    protected function put(string $endpoint, array $data = [], array $headers = []): array
    {
        $url = $this->buildUrl($endpoint);
        $headers = $this->buildHeaders($headers);
        
        $this->logger->logApiRequest('PUT', $url, $data, $headers);
        
        $startTime = microtime(true);
        $response = $this->httpClient->put($url, $data, $headers);
        $duration = microtime(true) - $startTime;
        
        $this->logger->logApiResponse(200, $response, $duration);
        
        return $this->handleResponse($response);
    }

    /**
     * 发送DELETE请求
     */
    protected function delete(string $endpoint, array $params = [], array $headers = []): array
    {
        $url = $this->buildUrl($endpoint);
        $headers = $this->buildHeaders($headers);
        
        $this->logger->logApiRequest('DELETE', $url, $params, $headers);
        
        $startTime = microtime(true);
        $response = $this->httpClient->delete($url, $params, $headers);
        $duration = microtime(true) - $startTime;
        
        $this->logger->logApiResponse(200, $response, $duration);
        
        return $this->handleResponse($response);
    }

    /**
     * 上传文件
     */
    protected function upload(string $endpoint, string $filePath, array $data = [], array $headers = []): array
    {
        $url = $this->buildUrl($endpoint);
        $headers = $this->buildHeaders($headers);
        
        $this->logger->logApiRequest('UPLOAD', $url, ['file' => basename($filePath)] + $data, $headers);
        
        $startTime = microtime(true);
        $response = $this->httpClient->upload($url, $filePath, $data, $headers);
        $duration = microtime(true) - $startTime;
        
        $this->logger->logApiResponse(200, $response, $duration);
        
        return $this->handleResponse($response);
    }

    /**
     * 构建完整URL
     */
    protected function buildUrl(string $endpoint): string
    {
        $baseUrl = $this->config->getApiBaseUrl();
        return rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');
    }

    /**
     * 构建请求头
     */
    protected function buildHeaders(array $headers = []): array
    {
        $defaultHeaders = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'DingTalk-PHP-SDK/' . $this->getVersion(),
        ];

        // 添加访问令牌
        $accessToken = $this->auth->getAccessToken();
        if ($accessToken) {
            $apiVersion = $this->config->get('api_version', 'v1');
            if ($apiVersion === 'v2') {
                $defaultHeaders['x-acs-dingtalk-access-token'] = $accessToken;
            } else {
                // V1版本通过查询参数传递
            }
        }

        return array_merge($defaultHeaders, $headers);
    }

    /**
     * 处理API响应
     */
    protected function handleResponse(array $response): array
    {
        // 检查错误
        if (isset($response['errcode']) && $response['errcode'] !== 0) {
            $this->handleApiError($response);
        }

        return $response;
    }

    /**
     * 处理API错误
     */
    protected function handleApiError(array $response): void
    {
        $errorCode = $response['errcode'] ?? 'UNKNOWN_ERROR';
        $errorMessage = $response['errmsg'] ?? 'Unknown error occurred';
        
        $this->logger->error('API Error', [
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'response' => $response,
        ]);

        throw new \DingTalk\Exceptions\ApiException(
            $errorMessage,
            $errorCode,
            $response
        );
    }

    /**
     * 缓存结果
     */
    protected function remember(string $key, callable $callback, int $ttl = 3600)
    {
        return $this->cache->remember($key, $callback, $ttl);
    }

    /**
     * 获取缓存
     */
    protected function getCache(string $key, $default = null)
    {
        return $this->cache->get($key, $default);
    }

    /**
     * 设置缓存
     */
    protected function setCache(string $key, $value, int $ttl = 3600): bool
    {
        return $this->cache->set($key, $value, $ttl);
    }

    /**
     * 删除缓存
     */
    protected function forgetCache(string $key): bool
    {
        return $this->cache->delete($key);
    }

    /**
     * 构建缓存键
     */
    protected function buildCacheKey(string $key, array $params = []): string
    {
        $appKey = $this->config->get('app_key');
        $prefix = 'dingtalk_' . md5($appKey) . '_';
        
        if (!empty($params)) {
            $key .= '_' . md5(serialize($params));
        }
        
        return $prefix . $key;
    }

    /**
     * 分页处理
     */
    protected function paginate(callable $callback, array $params = [], int $pageSize = 20): \Generator
    {
        $offset = 0;
        $hasMore = true;
        
        while ($hasMore) {
            $pageParams = array_merge($params, [
                'offset' => $offset,
                'size' => $pageSize,
            ]);
            
            $response = $callback($pageParams);
            
            if (isset($response['result'])) {
                $items = $response['result'];
                $hasMore = isset($response['has_more']) ? $response['has_more'] : false;
            } else {
                $items = $response;
                $hasMore = count($items) >= $pageSize;
            }
            
            foreach ($items as $item) {
                yield $item;
            }
            
            $offset += $pageSize;
            
            if (empty($items)) {
                break;
            }
        }
    }

    /**
     * 批量处理
     */
    protected function batch(array $items, callable $callback, int $batchSize = 100): array
    {
        $results = [];
        $batches = array_chunk($items, $batchSize);
        
        foreach ($batches as $batch) {
            $batchResults = $callback($batch);
            $results = array_merge($results, $batchResults);
        }
        
        return $results;
    }

    /**
     * 重试机制
     */
    protected function retry(callable $callback, int $maxAttempts = 3, int $delay = 1000): mixed
    {
        $attempt = 1;
        
        while ($attempt <= $maxAttempts) {
            try {
                return $callback();
            } catch (\Exception $e) {
                if ($attempt === $maxAttempts) {
                    throw $e;
                }
                
                $this->logger->warning('Retry attempt', [
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'error' => $e->getMessage(),
                ]);
                
                usleep($delay * 1000);
                $delay *= 2; // 指数退避
                $attempt++;
            }
        }
    }

    /**
     * 获取SDK版本
     */
    protected function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * 验证必需参数
     */
    protected function validateRequired(array $data, array $required): void
    {
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw new \InvalidArgumentException("Required field '{$field}' is missing or empty");
            }
        }
    }

    /**
     * 过滤空值
     */
    protected function filterEmpty(array $data): array
    {
        return array_filter($data, function ($value) {
            return $value !== null && $value !== '';
        });
    }

    /**
     * 转换时间戳
     */
    protected function toTimestamp($datetime): int
    {
        if (is_int($datetime)) {
            return $datetime;
        }
        
        if (is_string($datetime)) {
            return strtotime($datetime);
        }
        
        if ($datetime instanceof \DateTime) {
            return $datetime->getTimestamp();
        }
        
        throw new \InvalidArgumentException('Invalid datetime format');
    }
}