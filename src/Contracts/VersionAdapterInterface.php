<?php

declare(strict_types=1);

namespace DingTalk\Contracts;

/**
 * 版本适配器接口
 * 
 * 负责处理不同API版本之间的参数和响应格式转换
 */
interface VersionAdapterInterface
{
    /**
     * 转换请求参数格式
     * 
     * @param array $params 原始参数
     * @param string $fromVersion 源版本
     * @param string $toVersion 目标版本
     * @param string $method API方法名
     * @return array 转换后的参数
     */
    public function adaptRequestParams(array $params, string $fromVersion, string $toVersion, string $method): array;

    /**
     * 转换响应数据格式
     * 
     * @param array $response 原始响应数据
     * @param string $fromVersion 源版本
     * @param string $toVersion 目标版本
     * @param string $method API方法名
     * @return array 转换后的响应数据
     */
    public function adaptResponseData(array $response, string $fromVersion, string $toVersion, string $method): array;

    /**
     * 获取字段映射关系
     * 
     * @param string $fromVersion 源版本
     * @param string $toVersion 目标版本
     * @param string $method API方法名
     * @param string $type 映射类型 (request|response)
     * @return array 字段映射关系
     */
    public function getFieldMapping(string $fromVersion, string $toVersion, string $method, string $type): array;

    /**
     * 转换数据类型
     * 
     * @param mixed $value 原始值
     * @param string $fromType 源类型
     * @param string $toType 目标类型
     * @return mixed 转换后的值
     */
    public function convertDataType($value, string $fromType, string $toType);

    /**
     * 应用默认值
     * 
     * @param array $data 数据数组
     * @param string $version 版本
     * @param string $method API方法名
     * @param string $type 数据类型 (request|response)
     * @return array 应用默认值后的数据
     */
    public function applyDefaultValues(array $data, string $version, string $method, string $type): array;

    /**
     * 检查版本兼容性
     * 
     * @param string $fromVersion 源版本
     * @param string $toVersion 目标版本
     * @param string $method API方法名
     * @return bool 是否兼容
     */
    public function isCompatible(string $fromVersion, string $toVersion, string $method): bool;

    /**
     * 获取支持的版本列表
     * 
     * @return array 支持的版本列表
     */
    public function getSupportedVersions(): array;

    /**
     * 注册自定义适配规则
     * 
     * @param string $method API方法名
     * @param string $fromVersion 源版本
     * @param string $toVersion 目标版本
     * @param callable $adapter 适配器函数
     * @return void
     */
    public function registerCustomAdapter(string $method, string $fromVersion, string $toVersion, callable $adapter): void;
}