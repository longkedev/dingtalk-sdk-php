<?php

declare(strict_types=1);

namespace DingTalk\Contracts;

/**
 * HTTP客户端接口
 * 
 * 定义HTTP请求的标准接口
 */
interface HttpClientInterface
{
    /**
     * 发送GET请求
     * 
     * @param string $url 请求URL
     * @param array $query 查询参数
     * @param array $headers 请求头
     * @return array
     */
    public function get(string $url, array $query = [], array $headers = []): array;

    /**
     * 发送POST请求
     * 
     * @param string $url 请求URL
     * @param array $data 请求数据
     * @param array $headers 请求头
     * @return array
     */
    public function post(string $url, array $data = [], array $headers = []): array;

    /**
     * 发送PUT请求
     * 
     * @param string $url 请求URL
     * @param array $data 请求数据
     * @param array $headers 请求头
     * @return array
     */
    public function put(string $url, array $data = [], array $headers = []): array;

    /**
     * 发送DELETE请求
     * 
     * @param string $url 请求URL
     * @param array $query 查询参数
     * @param array $headers 请求头
     * @return array
     */
    public function delete(string $url, array $query = [], array $headers = []): array;

    /**
     * 发送PATCH请求
     * 
     * @param string $url 请求URL
     * @param array $data 请求数据
     * @param array $headers 请求头
     * @return array
     */
    public function patch(string $url, array $data = [], array $headers = []): array;

    /**
     * 上传文件
     * 
     * @param string $url 请求URL
     * @param array $files 文件数组
     * @param array $data 额外数据
     * @param array $headers 请求头
     * @return array
     */
    public function upload(string $url, array $files, array $data = [], array $headers = []): array;

    /**
     * 设置请求超时时间
     * 
     * @param int $timeout 超时时间（秒）
     */
    public function setTimeout(int $timeout): void;

    /**
     * 设置连接超时时间
     * 
     * @param int $timeout 连接超时时间（秒）
     */
    public function setConnectTimeout(int $timeout): void;

    /**
     * 设置重试次数
     * 
     * @param int $retries 重试次数
     */
    public function setRetries(int $retries): void;

    /**
     * 设置用户代理
     * 
     * @param string $userAgent 用户代理
     */
    public function setUserAgent(string $userAgent): void;

    /**
     * 获取最后一次请求的响应头
     * 
     * @return array
     */
    public function getLastResponseHeaders(): array;

    /**
     * 获取最后一次请求的状态码
     * 
     * @return int
     */
    public function getLastStatusCode(): int;

    /**
     * 添加中间件
     * 
     * @param callable $middleware 中间件函数
     * @param string $name 中间件名称
     */
    public function addMiddleware(callable $middleware, string $name = ''): void;

    /**
     * 移除中间件
     * 
     * @param string $name 中间件名称
     */
    public function removeMiddleware(string $name): void;

    /**
     * 设置连接池配置
     * 
     * @param array $config 连接池配置
     */
    public function setPoolConfig(array $config): void;

    /**
     * 批量请求
     * 
     * @param array $requests 请求数组
     * @param int $concurrency 并发数
     * @return array
     */
    public function batchRequest(array $requests, int $concurrency = null): array;

    /**
     * 设置重试延迟
     * 
     * @param int $delay 延迟时间（毫秒）
     */
    public function setRetryDelay(int $delay): void;
}