<?php

namespace DingTalk\Container\Providers;

use DingTalk\Container\ServiceProvider;
use DingTalk\Http\HttpClient;
use DingTalk\Contracts\HttpClientInterface;
use DingTalk\Contracts\ConfigInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * HTTP服务提供者
 * 
 * 负责注册HTTP客户端相关服务
 */
class HttpServiceProvider extends ServiceProvider
{
    /**
     * 延迟加载的服务
     *
     * @var array
     */
    protected $deferred = [
        HttpClientInterface::class,
        'http.client',
        'http'
    ];

    /**
     * 注册服务
     */
    public function register(): void
    {
        // 注册HTTP客户端
        $this->container->singleton(HttpClientInterface::class, function ($container) {
            $config = $container->get(ConfigInterface::class);
            
            // 尝试获取日志服务，如果不存在则使用空日志
            $logger = $container->bound(LoggerInterface::class) 
                ? $container->get(LoggerInterface::class) 
                : new NullLogger();
            
            return new HttpClient($config, $logger);
        });

        // 为HTTP客户端设置别名
        $this->container->alias(HttpClientInterface::class, 'http.client');
        $this->container->alias(HttpClientInterface::class, 'http');
    }

    /**
     * 启动服务
     */
    public function boot(): void
    {
        // 可以在这里进行HTTP客户端的额外配置
        if ($this->container->bound(HttpClientInterface::class)) {
            $httpClient = $this->container->get(HttpClientInterface::class);
            
            // 从配置中设置默认参数
            $config = $this->container->get(ConfigInterface::class);
            
            if ($config->has('http.timeout')) {
                $httpClient->setTimeout($config->get('http.timeout'));
            }
            
            if ($config->has('http.connect_timeout')) {
                $httpClient->setConnectTimeout($config->get('http.connect_timeout'));
            }
            
            if ($config->has('http.retries')) {
                $httpClient->setRetries($config->get('http.retries'));
            }
            
            if ($config->has('http.retry_delay')) {
                $httpClient->setRetryDelay($config->get('http.retry_delay'));
            }
            
            if ($config->has('http.pool')) {
                $httpClient->setPoolConfig($config->get('http.pool'));
            }
        }
    }

    /**
     * 获取延迟加载的服务
     *
     * @return array
     */
    public function provides(): array
    {
        return $this->deferred;
    }
}