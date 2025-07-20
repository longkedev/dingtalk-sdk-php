<?php

declare(strict_types=1);

namespace DingTalk\Contracts;

/**
 * 配置管理接口
 * 
 * 定义配置管理的标准接口
 */
interface ConfigInterface
{
    /**
     * 获取配置值
     * 
     * @param string $key 配置键
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * 设置配置值
     * 
     * @param string $key 配置键
     * @param mixed $value 配置值
     */
    public function set(string $key, $value): void;

    /**
     * 检查配置是否存在
     * 
     * @param string $key 配置键
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * 获取所有配置
     * 
     * @return array
     */
    public function all(): array;

    /**
     * 合并配置
     * 
     * @param array $config 要合并的配置
     */
    public function merge(array $config): void;
}