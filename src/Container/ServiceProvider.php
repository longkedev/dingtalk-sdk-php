<?php

declare(strict_types=1);

namespace DingTalk\Container;

use DingTalk\Contracts\ContainerInterface;

/**
 * 服务提供者抽象类
 * 
 * 定义服务提供者的基础结构
 */
abstract class ServiceProvider
{
    /**
     * 服务容器实例
     */
    protected ContainerInterface $container;

    /**
     * 延迟加载的服务
     */
    protected array $defer = [];

    /**
     * 构造函数
     * 
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * 注册服务
     */
    abstract public function register(): void;

    /**
     * 启动服务
     */
    public function boot(): void
    {
        // 默认实现为空，子类可以重写
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

    /**
     * 检查是否为延迟提供者
     * 
     * @return bool
     */
    public function isDeferred(): bool
    {
        return !empty($this->defer);
    }

    /**
     * 获取延迟服务
     * 
     * @return array
     */
    public function when(): array
    {
        return [];
    }
}