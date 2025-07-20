<?php

declare(strict_types=1);

namespace DingTalk\Http\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * HTTP中间件示例集合
 * 
 * 提供常用的HTTP中间件实现
 */
class HttpMiddleware
{
    /**
     * 认证中间件
     * 
     * @param string $token 访问令牌
     * @return callable
     */
    public static function auth(string $token): callable
    {
        return function (callable $handler) use ($token) {
            return function (RequestInterface $request, array $options) use ($handler, $token) {
                $request = $request->withHeader('Authorization', 'Bearer ' . $token);
                return $handler($request, $options);
            };
        };
    }

    /**
     * 请求限流中间件
     * 
     * @param int $maxRequests 最大请求数
     * @param int $timeWindow 时间窗口（秒）
     * @return callable
     */
    public static function rateLimit(int $maxRequests, int $timeWindow): callable
    {
        static $requests = [];
        
        return function (callable $handler) use ($maxRequests, $timeWindow, &$requests) {
            return function (RequestInterface $request, array $options) use ($handler, $maxRequests, $timeWindow, &$requests) {
                $now = time();
                $key = $request->getUri()->getHost();
                
                // 清理过期的请求记录
                if (isset($requests[$key])) {
                    $requests[$key] = array_filter($requests[$key], function ($timestamp) use ($now, $timeWindow) {
                        return $now - $timestamp < $timeWindow;
                    });
                }
                
                // 检查是否超过限制
                if (isset($requests[$key]) && count($requests[$key]) >= $maxRequests) {
                    throw new \RuntimeException('Rate limit exceeded');
                }
                
                // 记录当前请求
                $requests[$key][] = $now;
                
                return $handler($request, $options);
            };
        };
    }

    /**
     * 响应缓存中间件
     * 
     * @param int $ttl 缓存时间（秒）
     * @return callable
     */
    public static function cache(int $ttl = 300): callable
    {
        static $cache = [];
        
        return function (callable $handler) use ($ttl, &$cache) {
            return function (RequestInterface $request, array $options) use ($handler, $ttl, &$cache) {
                $cacheKey = md5($request->getMethod() . $request->getUri());
                $now = time();
                
                // 检查缓存
                if (isset($cache[$cacheKey]) && $now - $cache[$cacheKey]['time'] < $ttl) {
                    return $cache[$cacheKey]['response'];
                }
                
                // 执行请求
                $promise = $handler($request, $options);
                
                // 缓存响应
                return $promise->then(function (ResponseInterface $response) use ($cacheKey, $now, &$cache) {
                    if ($response->getStatusCode() === 200) {
                        $cache[$cacheKey] = [
                            'response' => $response,
                            'time' => $now,
                        ];
                    }
                    return $response;
                });
            };
        };
    }

    /**
     * 请求时间统计中间件
     * 
     * @param LoggerInterface $logger 日志记录器
     * @return callable
     */
    public static function timing(LoggerInterface $logger): callable
    {
        return function (callable $handler) use ($logger) {
            return function (RequestInterface $request, array $options) use ($handler, $logger) {
                $start = microtime(true);
                
                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use ($request, $start, $logger) {
                        $duration = microtime(true) - $start;
                        $logger->info('Request completed', [
                            'method' => $request->getMethod(),
                            'uri' => (string) $request->getUri(),
                            'status' => $response->getStatusCode(),
                            'duration' => round($duration * 1000, 2) . 'ms',
                        ]);
                        return $response;
                    },
                    function ($reason) use ($request, $start, $logger) {
                        $duration = microtime(true) - $start;
                        $logger->error('Request failed', [
                            'method' => $request->getMethod(),
                            'uri' => (string) $request->getUri(),
                            'duration' => round($duration * 1000, 2) . 'ms',
                            'error' => $reason->getMessage(),
                        ]);
                        throw $reason;
                    }
                );
            };
        };
    }

    /**
     * 请求头添加中间件
     * 
     * @param array $headers 要添加的请求头
     * @return callable
     */
    public static function addHeaders(array $headers): callable
    {
        return function (callable $handler) use ($headers) {
            return function (RequestInterface $request, array $options) use ($handler, $headers) {
                foreach ($headers as $name => $value) {
                    $request = $request->withHeader($name, $value);
                }
                return $handler($request, $options);
            };
        };
    }

    /**
     * 请求体压缩中间件
     * 
     * @return callable
     */
    public static function compression(): callable
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                // 添加压缩支持
                $request = $request->withHeader('Accept-Encoding', 'gzip, deflate');
                return $handler($request, $options);
            };
        };
    }
}