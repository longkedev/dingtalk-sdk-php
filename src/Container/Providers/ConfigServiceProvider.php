<?php

declare(strict_types=1);

namespace DingTalk\Container\Providers;

use DingTalk\Container\ServiceProvider;
use DingTalk\Config\ConfigManager;
use DingTalk\Contracts\ConfigInterface;

/**
 * 配置服务提供者
 * 
 * 提供配置管理相关服务
 */
class ConfigServiceProvider extends ServiceProvider
{
    /**
     * 延迟加载的服务
     */
    protected array $defer = [
        ConfigInterface::class,
        'config',
    ];

    /**
     * 注册服务
     */
    public function register(): void
    {
        $this->container->singleton(ConfigInterface::class, function ($container) {
            return new ConfigManager();
        });

        $this->container->alias(ConfigInterface::class, 'config');
    }

    /**
     * 启动服务
     */
    public function boot(): void
    {
        // 配置服务启动逻辑
    }

    /**
     * 获取提供的服务
     * 
     * @return array
     */
    public function provides(): array
    {
        return $this->defer;
    }
}