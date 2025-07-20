<?php

declare(strict_types=1);

namespace DingTalk\Contracts;

/**
 * 缓存接口
 * 
 * 定义缓存操作的标准接口
 */
interface CacheInterface
{
    /**
     * 获取缓存值
     * 
     * @param string $key 缓存键
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * 设置缓存值
     * 
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int|null $ttl 过期时间（秒）
     * @return bool
     */
    public function set(string $key, $value, ?int $ttl = null): bool;

    /**
     * 删除缓存
     * 
     * @param string $key 缓存键
     * @return bool
     */
    public function delete(string $key): bool;

    /**
     * 清空所有缓存
     * 
     * @return bool
     */
    public function clear(): bool;

    /**
     * 检查缓存是否存在
     * 
     * @param string $key 缓存键
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * 批量获取缓存
     * 
     * @param array $keys 缓存键数组
     * @param mixed $default 默认值
     * @return array
     */
    public function getMultiple(array $keys, $default = null): array;

    /**
     * 批量设置缓存
     * 
     * @param array $values 键值对数组
     * @param int|null $ttl 过期时间（秒）
     * @return bool
     */
    public function setMultiple(array $values, ?int $ttl = null): bool;

    /**
     * 批量删除缓存
     * 
     * @param array $keys 缓存键数组
     * @return bool
     */
    public function deleteMultiple(array $keys): bool;

    /**
     * 增加缓存值
     * 
     * @param string $key 缓存键
     * @param int $value 增加的值
     * @return int|false
     */
    public function increment(string $key, int $value = 1);

    /**
     * 减少缓存值
     * 
     * @param string $key 缓存键
     * @param int $value 减少的值
     * @return int|false
     */
    public function decrement(string $key, int $value = 1);

    /**
     * 记住缓存值（如果不存在则设置）
     * 
     * @param string $key 缓存键
     * @param callable $callback 回调函数
     * @param int|null $ttl 过期时间（秒）
     * @return mixed
     */
    public function remember(string $key, callable $callback, ?int $ttl = null);

    /**
     * 永久记住缓存值
     * 
     * @param string $key 缓存键
     * @param callable $callback 回调函数
     * @return mixed
     */
    public function rememberForever(string $key, callable $callback);

    /**
     * 忘记缓存值
     * 
     * @param string $key 缓存键
     * @return bool
     */
    public function forget(string $key): bool;

    /**
     * 刷新缓存
     * 
     * @return bool
     */
    public function flush(): bool;
}