<?php

declare(strict_types=1);

namespace DingTalk\Http;

use DingTalk\Contracts\HttpClientInterface;
use DingTalk\Contracts\ConfigInterface;
use DingTalk\Exceptions\ApiException;
use DingTalk\Exceptions\NetworkException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * HTTP客户端
 * 
 * 基于Guzzle实现的HTTP客户端
 */
class HttpClient implements HttpClientInterface
{
    /**
     * Guzzle客户端
     */
    private Client $client;

    /**
     * 配置管理器
     */
    private ConfigInterface $config;

    /**
     * 日志记录器
     */
    private LoggerInterface $logger;

    /**
     * 中间件栈
     */
    private array $middlewares = [];

    /**
     * 重试次数
     */
    private int $retries = 3;

    /**
     * 重试延迟（毫秒）
     */
    private int $retryDelay = 1000;

    /**
     * 连接池配置
     */
    private array $poolConfig = [
        'concurrency' => 10,
        'max_connections' => 100,
    ];

    /**
     * 最后一次响应头
     */
    private array $lastResponseHeaders = [];

    /**
     * 最后一次状态码
     */
    private int $lastStatusCode = 0;

    /**
     * 构造函数
     */
    public function __construct(ConfigInterface $config, ?LoggerInterface $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger ?? new NullLogger();
        
        // 从配置中读取重试和连接池设置
        $this->retries = $this->config->get('http.retries', 3);
        $this->retryDelay = $this->config->get('http.retry_delay', 1000);
        $this->poolConfig = array_merge($this->poolConfig, $this->config->get('http.pool', []));
        
        // 添加默认中间件
        $this->addDefaultMiddlewares();
        
        $this->client = $this->createClient();
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $url, array $query = [], array $headers = []): array
    {
        return $this->request('GET', $url, [
            RequestOptions::QUERY => $query,
            RequestOptions::HEADERS => $headers,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function post(string $url, array $data = [], array $headers = []): array
    {
        return $this->request('POST', $url, [
            RequestOptions::JSON => $data,
            RequestOptions::HEADERS => $headers,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $url, array $data = [], array $headers = []): array
    {
        return $this->request('PUT', $url, [
            RequestOptions::JSON => $data,
            RequestOptions::HEADERS => $headers,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $url, array $query = [], array $headers = []): array
    {
        return $this->request('DELETE', $url, [
            RequestOptions::QUERY => $query,
            RequestOptions::HEADERS => $headers,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function patch(string $url, array $data = [], array $headers = []): array
    {
        return $this->request('PATCH', $url, [
            RequestOptions::JSON => $data,
            RequestOptions::HEADERS => $headers,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function upload(string $url, array $files, array $data = [], array $headers = []): array
    {
        $multipart = [];

        // 添加文件
        foreach ($files as $name => $file) {
            $multipart[] = [
                'name' => $name,
                'contents' => is_resource($file) ? $file : fopen($file, 'r'),
                'filename' => is_string($file) ? basename($file) : null,
            ];
        }

        // 添加其他数据
        foreach ($data as $name => $value) {
            $multipart[] = [
                'name' => $name,
                'contents' => $value,
            ];
        }

        return $this->request('POST', $url, [
            RequestOptions::MULTIPART => $multipart,
            RequestOptions::HEADERS => $headers,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function setTimeout(int $timeout): void
    {
        $this->client = $this->createClient(['timeout' => $timeout]);
    }

    /**
     * {@inheritdoc}
     */
    public function setConnectTimeout(int $timeout): void
    {
        $this->client = $this->createClient(['connect_timeout' => $timeout]);
    }

    /**
     * {@inheritdoc}
     */
    public function setRetries(int $retries): void
    {
        $this->retries = $retries;
        // 重新创建客户端以应用新的重试配置
        $this->client = $this->createClient();
    }

    /**
     * {@inheritdoc}
     */
    public function setUserAgent(string $userAgent): void
    {
        $this->client = $this->createClient([
            'headers' => ['User-Agent' => $userAgent]
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getLastResponseHeaders(): array
    {
        return $this->lastResponseHeaders;
    }

    /**
     * {@inheritdoc}
     */
    public function getLastStatusCode(): int
    {
        return $this->lastStatusCode;
    }

    /**
     * 添加中间件
     * 
     * @param callable $middleware 中间件函数
     * @param string $name 中间件名称
     */
    public function addMiddleware(callable $middleware, string $name = ''): void
    {
        $this->middlewares[$name ?: uniqid()] = $middleware;
        // 重新创建客户端以应用新的中间件
        $this->client = $this->createClient();
    }

    /**
     * 移除中间件
     * 
     * @param string $name 中间件名称
     */
    public function removeMiddleware(string $name): void
    {
        unset($this->middlewares[$name]);
        // 重新创建客户端以应用变更
        $this->client = $this->createClient();
    }

    /**
     * 设置连接池配置
     * 
     * @param array $config 连接池配置
     */
    public function setPoolConfig(array $config): void
    {
        $this->poolConfig = array_merge($this->poolConfig, $config);
    }

    /**
     * 批量请求
     * 
     * @param array $requests 请求数组
     * @param int $concurrency 并发数
     * @return array
     */
    public function batchRequest(array $requests, int $concurrency = null): array
    {
        $concurrency = $concurrency ?? $this->poolConfig['concurrency'];
        $results = [];
        
        $pool = new Pool($this->client, $requests, [
            'concurrency' => $concurrency,
            'fulfilled' => function (ResponseInterface $response, $index) use (&$results) {
                $results[$index] = [
                    'success' => true,
                    'data' => $this->parseResponse($response),
                    'status_code' => $response->getStatusCode(),
                    'headers' => $this->parseHeaders($response),
                ];
            },
            'rejected' => function ($reason, $index) use (&$results) {
                $results[$index] = [
                    'success' => false,
                    'error' => $reason->getMessage(),
                    'status_code' => $reason instanceof RequestException && $reason->getResponse() 
                        ? $reason->getResponse()->getStatusCode() 
                        : 0,
                ];
            },
        ]);

        $promise = $pool->promise();
        $promise->wait();

        return $results;
    }

    /**
     * 设置重试延迟
     * 
     * @param int $delay 延迟时间（毫秒）
     */
    public function setRetryDelay(int $delay): void
    {
        $this->retryDelay = $delay;
        // 重新创建客户端以应用新的重试配置
        $this->client = $this->createClient();
    }

    /**
     * 发送请求
     */
    private function request(string $method, string $url, array $options = []): array
    {
        $startTime = microtime(true);
        
        // 记录请求日志
        $this->logger->info('HTTP Request', [
            'method' => $method,
            'url' => $url,
            'options' => $this->sanitizeLogData($options),
        ]);

        try {
            $response = $this->client->request($method, $url, $options);
            
            $this->lastStatusCode = $response->getStatusCode();
            $this->lastResponseHeaders = $this->parseHeaders($response);
            
            $responseData = $this->parseResponse($response);
            $duration = microtime(true) - $startTime;
            
            // 记录响应日志
            $this->logger->info('HTTP Response', [
                'method' => $method,
                'url' => $url,
                'status_code' => $this->lastStatusCode,
                'duration' => round($duration * 1000, 2) . 'ms',
                'response_size' => strlen(json_encode($responseData)),
            ]);
            
            return $responseData;
        } catch (RequestException $e) {
            $duration = microtime(true) - $startTime;
            
            // 记录错误日志
            $this->logger->error('HTTP Request Failed', [
                'method' => $method,
                'url' => $url,
                'duration' => round($duration * 1000, 2) . 'ms',
                'error' => $e->getMessage(),
                'status_code' => $e->getResponse() ? $e->getResponse()->getStatusCode() : 0,
            ]);
            
            $this->handleRequestException($e);
        } catch (GuzzleException $e) {
            $duration = microtime(true) - $startTime;
            
            // 记录错误日志
            $this->logger->error('HTTP Request Exception', [
                'method' => $method,
                'url' => $url,
                'duration' => round($duration * 1000, 2) . 'ms',
                'error' => $e->getMessage(),
            ]);
            
            throw new ApiException(
                'HTTP request failed: ' . $e->getMessage(),
                'HTTP_REQUEST_FAILED',
                ['url' => $url, 'method' => $method],
                0,
                $e
            );
        }
    }

    /**
     * 添加默认中间件
     */
    private function addDefaultMiddlewares(): void
    {
        // 请求ID中间件
        $this->addMiddleware(function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                $request = $request->withHeader('X-Request-ID', uniqid('req_'));
                return $handler($request, $options);
            };
        }, 'request_id');

        // 用户代理中间件
        $this->addMiddleware(function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                if (!$request->hasHeader('User-Agent')) {
                    $request = $request->withHeader('User-Agent', 
                        $this->config->get('user_agent', 'DingTalk-PHP-SDK/1.0.0')
                    );
                }
                return $handler($request, $options);
            };
        }, 'user_agent');
    }

    /**
     * 清理日志数据（移除敏感信息）
     */
    private function sanitizeLogData(array $data): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'key', 'authorization'];
        
        return $this->recursiveSanitize($data, $sensitiveKeys);
    }

    /**
     * 递归清理敏感数据
     */
    private function recursiveSanitize(array $data, array $sensitiveKeys): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->recursiveSanitize($value, $sensitiveKeys);
            } elseif (is_string($key) && in_array(strtolower($key), $sensitiveKeys)) {
                $data[$key] = '***REDACTED***';
            }
        }
        
        return $data;
    }

    /**
     * 创建Guzzle客户端
     */
    private function createClient(array $options = []): Client
    {
        // 创建处理器栈
        $stack = HandlerStack::create();
        
        // 添加重试中间件
        if ($this->retries > 0) {
            $stack->push(Middleware::retry(
                function ($retries, RequestInterface $request, ResponseInterface $response = null, $exception = null) {
                    // 重试条件：网络错误或5xx状态码
                    if ($retries >= $this->retries) {
                        return false;
                    }
                    
                    if ($exception instanceof RequestException) {
                        return true;
                    }
                    
                    if ($response && $response->getStatusCode() >= 500) {
                        return true;
                    }
                    
                    return false;
                },
                function ($retries) {
                    // 指数退避延迟
                    return $this->retryDelay * pow(2, $retries - 1);
                }
            ), 'retry');
        }
        
        // 添加自定义中间件
        foreach ($this->middlewares as $name => $middleware) {
            $stack->push($middleware, $name);
        }

        $defaultOptions = [
            'handler' => $stack,
            RequestOptions::TIMEOUT => $this->config->get('http.timeout', 30),
            RequestOptions::CONNECT_TIMEOUT => $this->config->get('http.connect_timeout', 10),
            RequestOptions::VERIFY => $this->config->get('security.validate_ssl', true),
            RequestOptions::HEADERS => [
                'User-Agent' => $this->config->get('user_agent', 'DingTalk-PHP-SDK/1.0.0'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            // 连接池配置
            RequestOptions::CURL => [
                CURLOPT_MAXCONNECTS => $this->poolConfig['max_connections'],
            ],
        ];

        return new Client(array_merge($defaultOptions, $options));
    }

    /**
     * 解析响应
     */
    private function parseResponse(ResponseInterface $response): array
    {
        $content = $response->getBody()->getContents();
        
        if (empty($content)) {
            return [];
        }

        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException(
                'Invalid JSON response: ' . json_last_error_msg(),
                'INVALID_JSON_RESPONSE',
                ['content' => $content]
            );
        }

        // 检查钉钉API错误
        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new ApiException(
                $data['errmsg'] ?? 'Unknown API error',
                (string) $data['errcode'],
                $data
            );
        }

        return $data;
    }

    /**
     * 解析响应头
     */
    private function parseHeaders(ResponseInterface $response): array
    {
        $headers = [];
        
        foreach ($response->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }
        
        return $headers;
    }

    /**
     * 处理请求异常
     */
    private function handleRequestException(RequestException $e): void
    {
        $response = $e->getResponse();
        
        if ($response) {
            $this->lastStatusCode = $response->getStatusCode();
            $this->lastResponseHeaders = $this->parseHeaders($response);
            
            try {
                $data = $this->parseResponse($response);
            } catch (ApiException $parseException) {
                // 如果解析失败，抛出原始异常
                throw new ApiException(
                    'HTTP request failed: ' . $e->getMessage(),
                    'HTTP_REQUEST_FAILED',
                    ['status_code' => $this->lastStatusCode],
                    $this->lastStatusCode,
                    $e
                );
            }
        } else {
            throw new ApiException(
                'HTTP request failed: ' . $e->getMessage(),
                'HTTP_REQUEST_FAILED',
                [],
                0,
                $e
            );
        }
    }
}