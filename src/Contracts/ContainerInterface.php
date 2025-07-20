<?php

declare(strict_types=1);

namespace DingTalk\Contracts;

use Closure;

/**
 * 服务容器接口
 * 
 * 定义依赖注入容器的标准接口
 */
interface ContainerInterface
{
    /**
     * 绑定服务到容器
     * 
     * @param string $abstract 抽象标识
     * @param mixed $concrete 具体实现
     * @param bool $shared 是否共享实例
     */
    public function bind(string $abstract, $concrete = null, bool $shared = false): void;

    /**
     * 绑定单例服务到容器
     * 
     * @param string $abstract 抽象标识
     * @param mixed $concrete 具体实现
     */
    public function singleton(string $abstract, $concrete = null): void;

    /**
     * 绑定实例到容器
     * 
     * @param string $abstract 抽象标识
     * @param mixed $instance 实例
     */
    public function instance(string $abstract, $instance): void;

    /**
     * 从容器中解析服务
     * 
     * @param string $abstract 抽象标识
     * @param array $parameters 参数
     * @return mixed
     */
    public function get(string $abstract, array $parameters = []);

    /**
     * 从容器中创建服务实例
     * 
     * @param string $abstract 抽象标识
     * @param array $parameters 参数
     * @return mixed
     */
    public function make(string $abstract, array $parameters = []);

    /**
     * 检查服务是否已绑定
     * 
     * @param string $abstract 抽象标识
     * @return bool
     */
    public function bound(string $abstract): bool;

    /**
     * 检查服务是否为单例
     * 
     * @param string $abstract 抽象标识
     * @return bool
     */
    public function isShared(string $abstract): bool;

    /**
     * 调用方法并注入依赖
     * 
     * @param callable|string $callback 回调函数或方法
     * @param array $parameters 参数
     * @return mixed
     */
    public function call($callback, array $parameters = []);

    /**
     * 扩展服务
     * 
     * @param string $abstract 抽象标识
     * @param Closure $closure 扩展闭包
     */
    public function extend(string $abstract, Closure $closure): void;

    /**
     * 标记服务
     * 
     * @param array|string $abstracts 抽象标识
     * @param string $tag 标签
     */
    public function tag($abstracts, string $tag): void;

    /**
     * 获取标记的服务
     * 
     * @param string $tag 标签
     * @return array
     */
    public function tagged(string $tag): array;

    /**
     * 刷新容器
     * 
     * @param string $abstract 抽象标识
     */
    public function refresh(string $abstract): void;

    /**
     * 为服务设置别名
     * 
     * @param string $abstract 抽象标识
     * @param string $alias 别名
     */
    public function alias(string $abstract, string $alias): void;
}