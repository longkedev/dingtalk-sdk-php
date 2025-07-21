<?php

declare(strict_types=1);

namespace DingTalk\Version;

use DingTalk\Contracts\VersionAdapterInterface;
use DingTalk\Contracts\ConfigInterface;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * 版本适配器实现类
 * 
 * 实现不同API版本之间的参数和响应格式转换
 */
class VersionAdapter implements VersionAdapterInterface
{
    /**
     * 支持的版本列表
     */
    public const SUPPORTED_VERSIONS = ['v1', 'v2'];

    /**
     * 请求类型
     */
    public const TYPE_REQUEST = 'request';

    /**
     * 响应类型
     */
    public const TYPE_RESPONSE = 'response';

    /**
     * 数据类型映射
     */
    private const DATA_TYPE_MAPPING = [
        'string' => 'string',
        'int' => 'integer',
        'integer' => 'integer',
        'float' => 'float',
        'double' => 'float',
        'bool' => 'boolean',
        'boolean' => 'boolean',
        'array' => 'array',
        'object' => 'object',
        'null' => 'null'
    ];

    /**
     * @var ConfigInterface 配置管理器
     */
    private ConfigInterface $config;

    /**
     * @var LoggerInterface 日志记录器
     */
    private LoggerInterface $logger;

    /**
     * @var array 字段映射配置
     */
    private array $fieldMappings = [];

    /**
     * @var array 默认值配置
     */
    private array $defaultValues = [];

    /**
     * @var array 自定义适配器
     */
    private array $customAdapters = [];

    /**
     * @var array 兼容性矩阵
     */
    private array $compatibilityMatrix = [];

    /**
     * @var array 适配统计
     */
    private array $adaptationStats = [
        'total_adaptations' => 0,
        'successful_adaptations' => 0,
        'failed_adaptations' => 0,
        'method_stats' => []
    ];

    /**
     * 构造函数
     * 
     * @param ConfigInterface $config 配置管理器
     * @param LoggerInterface $logger 日志记录器
     */
    public function __construct(ConfigInterface $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        
        $this->initializeFieldMappings();
        $this->initializeDefaultValues();
        $this->initializeCompatibilityMatrix();
    }

    /**
     * 转换请求参数格式
     * 
     * @param array $params 原始参数
     * @param string $fromVersion 源版本
     * @param string $toVersion 目标版本
     * @param string $method API方法名
     * @return array 转换后的参数
     */
    public function adaptRequestParams(array $params, string $fromVersion, string $toVersion, string $method): array
    {
        $this->validateVersions($fromVersion, $toVersion);
        
        $this->adaptationStats['total_adaptations']++;
        $this->updateMethodStats($method, 'request');

        try {
            // 检查是否有自定义适配器
            if ($this->hasCustomAdapter($method, $fromVersion, $toVersion)) {
                $result = $this->applyCustomAdapter($params, $method, $fromVersion, $toVersion);
                $this->adaptationStats['successful_adaptations']++;
                return $result;
            }

            // 应用字段映射
            $mappedParams = $this->applyFieldMapping($params, $fromVersion, $toVersion, $method, self::TYPE_REQUEST);
            
            // 转换数据类型
            $convertedParams = $this->convertParamTypes($mappedParams, $fromVersion, $toVersion, $method);
            
            // 应用默认值
            $finalParams = $this->applyDefaultValues($convertedParams, $toVersion, $method, self::TYPE_REQUEST);

            $this->adaptationStats['successful_adaptations']++;
            
            $this->logger->info('Request parameters adapted successfully', [
                'method' => $method,
                'from_version' => $fromVersion,
                'to_version' => $toVersion,
                'original_count' => count($params),
                'adapted_count' => count($finalParams)
            ]);

            return $finalParams;

        } catch (\Exception $e) {
            $this->adaptationStats['failed_adaptations']++;
            
            $this->logger->error('Failed to adapt request parameters', [
                'method' => $method,
                'from_version' => $fromVersion,
                'to_version' => $toVersion,
                'error' => $e->getMessage(),
                'params' => $params
            ]);

            throw new RuntimeException("Failed to adapt request parameters: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 转换响应数据格式
     * 
     * @param array $response 原始响应数据
     * @param string $fromVersion 源版本
     * @param string $toVersion 目标版本
     * @param string $method API方法名
     * @return array 转换后的响应数据
     */
    public function adaptResponseData(array $response, string $fromVersion, string $toVersion, string $method): array
    {
        $this->validateVersions($fromVersion, $toVersion);
        
        $this->adaptationStats['total_adaptations']++;
        $this->updateMethodStats($method, 'response');

        try {
            // 检查是否有自定义适配器
            if ($this->hasCustomAdapter($method, $fromVersion, $toVersion)) {
                $result = $this->applyCustomAdapter($response, $method, $fromVersion, $toVersion);
                $this->adaptationStats['successful_adaptations']++;
                return $result;
            }

            // 应用字段映射
            $mappedResponse = $this->applyFieldMapping($response, $fromVersion, $toVersion, $method, self::TYPE_RESPONSE);
            
            // 转换数据类型
            $convertedResponse = $this->convertResponseTypes($mappedResponse, $fromVersion, $toVersion, $method);
            
            // 应用默认值
            $finalResponse = $this->applyDefaultValues($convertedResponse, $toVersion, $method, self::TYPE_RESPONSE);

            $this->adaptationStats['successful_adaptations']++;
            
            $this->logger->info('Response data adapted successfully', [
                'method' => $method,
                'from_version' => $fromVersion,
                'to_version' => $toVersion,
                'original_fields' => count($response),
                'adapted_fields' => count($finalResponse)
            ]);

            return $finalResponse;

        } catch (\Exception $e) {
            $this->adaptationStats['failed_adaptations']++;
            
            $this->logger->error('Failed to adapt response data', [
                'method' => $method,
                'from_version' => $fromVersion,
                'to_version' => $toVersion,
                'error' => $e->getMessage(),
                'response' => $response
            ]);

            throw new RuntimeException("Failed to adapt response data: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 获取字段映射关系
     * 
     * @param string $fromVersion 源版本
     * @param string $toVersion 目标版本
     * @param string $method API方法名
     * @param string $type 映射类型 (request|response)
     * @return array 字段映射关系
     */
    public function getFieldMapping(string $fromVersion, string $toVersion, string $method, string $type): array
    {
        $key = "{$fromVersion}_{$toVersion}_{$method}_{$type}";
        return $this->fieldMappings[$key] ?? [];
    }

    /**
     * 转换数据类型
     * 
     * @param mixed $value 原始值
     * @param string $fromType 源类型
     * @param string $toType 目标类型
     * @return mixed 转换后的值
     */
    public function convertDataType($value, string $fromType, string $toType)
    {
        if ($fromType === $toType) {
            return $value;
        }

        // 处理null值
        if ($value === null) {
            return $this->getDefaultValueForType($toType);
        }

        try {
            switch ($toType) {
                case 'string':
                    return (string) $value;
                
                case 'integer':
                case 'int':
                    return (int) $value;
                
                case 'float':
                case 'double':
                    return (float) $value;
                
                case 'boolean':
                case 'bool':
                    return (bool) $value;
                
                case 'array':
                    return is_array($value) ? $value : [$value];
                
                case 'object':
                    return is_object($value) ? $value : (object) $value;
                
                default:
                    return $value;
            }
        } catch (\Exception $e) {
            $this->logger->warning('Data type conversion failed', [
                'value' => $value,
                'from_type' => $fromType,
                'to_type' => $toType,
                'error' => $e->getMessage()
            ]);
            
            return $value;
        }
    }

    /**
     * 应用默认值
     * 
     * @param array $data 数据数组
     * @param string $version 版本
     * @param string $method API方法名
     * @param string $type 数据类型 (request|response)
     * @return array 应用默认值后的数据
     */
    public function applyDefaultValues(array $data, string $version, string $method, string $type): array
    {
        $key = "{$version}_{$method}_{$type}";
        $defaults = $this->defaultValues[$key] ?? [];

        foreach ($defaults as $field => $defaultValue) {
            if (!isset($data[$field]) || $data[$field] === null) {
                $data[$field] = $defaultValue;
            }
        }

        return $data;
    }

    /**
     * 检查版本兼容性
     * 
     * @param string $fromVersion 源版本
     * @param string $toVersion 目标版本
     * @param string $method API方法名
     * @return bool 是否兼容
     */
    public function isCompatible(string $fromVersion, string $toVersion, string $method): bool
    {
        $key = "{$fromVersion}_{$toVersion}_{$method}";
        return $this->compatibilityMatrix[$key] ?? false;
    }

    /**
     * 获取支持的版本列表
     * 
     * @return array 支持的版本列表
     */
    public function getSupportedVersions(): array
    {
        return self::SUPPORTED_VERSIONS;
    }

    /**
     * 注册自定义适配规则
     * 
     * @param string $method API方法名
     * @param string $fromVersion 源版本
     * @param string $toVersion 目标版本
     * @param callable $adapter 适配器函数
     * @return void
     */
    public function registerCustomAdapter(string $method, string $fromVersion, string $toVersion, callable $adapter): void
    {
        $key = "{$method}_{$fromVersion}_{$toVersion}";
        $this->customAdapters[$key] = $adapter;
        
        $this->logger->info('Custom adapter registered', [
            'method' => $method,
            'from_version' => $fromVersion,
            'to_version' => $toVersion
        ]);
    }

    /**
     * 获取适配统计信息
     * 
     * @return array 统计信息
     */
    public function getAdaptationStats(): array
    {
        return $this->adaptationStats;
    }

    /**
     * 清除适配统计信息
     * 
     * @return void
     */
    public function clearAdaptationStats(): void
    {
        $this->adaptationStats = [
            'total_adaptations' => 0,
            'successful_adaptations' => 0,
            'failed_adaptations' => 0,
            'method_stats' => []
        ];
    }

    /**
     * 初始化字段映射配置
     * 
     * @return void
     */
    private function initializeFieldMappings(): void
    {
        // V1 到 V2 的字段映射
        $this->fieldMappings = [
            // 用户相关API映射
            'v1_v2_user.get_request' => [
                'userid' => 'user_id',
                'lang' => 'language'
            ],
            'v1_v2_user.get_response' => [
                'userid' => 'user_id',
                'name' => 'user_name',
                'mobile' => 'phone_number',
                'department' => 'dept_id_list'
            ],
            
            // 部门相关API映射
            'v1_v2_department.list_request' => [
                'id' => 'dept_id',
                'fetch_child' => 'include_children'
            ],
            'v1_v2_department.list_response' => [
                'id' => 'dept_id',
                'name' => 'dept_name',
                'parentid' => 'parent_id'
            ],
            
            // 消息相关API映射
            'v1_v2_message.send_request' => [
                'touser' => 'user_id_list',
                'toparty' => 'dept_id_list',
                'msgtype' => 'msg_type'
            ],
            'v1_v2_message.send_response' => [
                'task_id' => 'message_id'
            ]
        ];

        // V2 到 V1 的字段映射（反向映射）
        foreach ($this->fieldMappings as $key => $mapping) {
            if (strpos($key, 'v1_v2_') === 0) {
                $reverseKey = str_replace('v1_v2_', 'v2_v1_', $key);
                $this->fieldMappings[$reverseKey] = array_flip($mapping);
            }
        }
    }

    /**
     * 初始化默认值配置
     * 
     * @return void
     */
    private function initializeDefaultValues(): void
    {
        $this->defaultValues = [
            // V1版本默认值
            'v1_user.get_request' => [
                'lang' => 'zh_CN'
            ],
            'v1_message.send_request' => [
                'safe' => 0
            ],
            
            // V2版本默认值
            'v2_user.get_request' => [
                'language' => 'zh_CN'
            ],
            'v2_message.send_request' => [
                'enable_duplicate_check' => false,
                'duplicate_check_interval' => 1800
            ]
        ];
    }

    /**
     * 初始化兼容性矩阵
     * 
     * @return void
     */
    private function initializeCompatibilityMatrix(): void
    {
        $this->compatibilityMatrix = [
            // 用户管理API兼容性
            'v1_v2_user.get' => true,
            'v1_v2_user.create' => true,
            'v1_v2_user.update' => true,
            'v1_v2_user.delete' => true,
            
            // 部门管理API兼容性
            'v1_v2_department.list' => true,
            'v1_v2_department.get' => true,
            'v1_v2_department.create' => true,
            'v1_v2_department.update' => true,
            
            // 消息发送API兼容性
            'v1_v2_message.send' => true,
            'v1_v2_message.recall' => false, // V1不支持撤回
            
            // 反向兼容性
            'v2_v1_user.get' => true,
            'v2_v1_user.create' => true,
            'v2_v1_user.update' => true,
            'v2_v1_user.delete' => true,
            'v2_v1_department.list' => true,
            'v2_v1_department.get' => true,
            'v2_v1_department.create' => true,
            'v2_v1_department.update' => true,
            'v2_v1_message.send' => true,
            'v2_v1_message.recall' => false
        ];
    }

    /**
     * 验证版本参数
     * 
     * @param string $fromVersion 源版本
     * @param string $toVersion 目标版本
     * @return void
     * @throws InvalidArgumentException
     */
    private function validateVersions(string $fromVersion, string $toVersion): void
    {
        if (!in_array($fromVersion, self::SUPPORTED_VERSIONS)) {
            throw new InvalidArgumentException("Unsupported source version: {$fromVersion}");
        }

        if (!in_array($toVersion, self::SUPPORTED_VERSIONS)) {
            throw new InvalidArgumentException("Unsupported target version: {$toVersion}");
        }
    }

    /**
     * 应用字段映射
     * 
     * @param array $data 原始数据
     * @param string $fromVersion 源版本
     * @param string $toVersion 目标版本
     * @param string $method API方法名
     * @param string $type 数据类型
     * @return array 映射后的数据
     */
    private function applyFieldMapping(array $data, string $fromVersion, string $toVersion, string $method, string $type): array
    {
        $mapping = $this->getFieldMapping($fromVersion, $toVersion, $method, $type);
        
        if (empty($mapping)) {
            return $data;
        }

        $mappedData = [];
        
        foreach ($data as $key => $value) {
            $newKey = $mapping[$key] ?? $key;
            $mappedData[$newKey] = $value;
        }

        return $mappedData;
    }

    /**
     * 转换请求参数类型
     * 
     * @param array $params 参数数组
     * @param string $fromVersion 源版本
     * @param string $toVersion 目标版本
     * @param string $method API方法名
     * @return array 转换后的参数
     */
    private function convertParamTypes(array $params, string $fromVersion, string $toVersion, string $method): array
    {
        // 这里可以根据具体的API方法定义类型转换规则
        // 目前使用通用的类型推断
        return $this->convertArrayTypes($params);
    }

    /**
     * 转换响应数据类型
     * 
     * @param array $response 响应数据
     * @param string $fromVersion 源版本
     * @param string $toVersion 目标版本
     * @param string $method API方法名
     * @return array 转换后的响应
     */
    private function convertResponseTypes(array $response, string $fromVersion, string $toVersion, string $method): array
    {
        // 这里可以根据具体的API方法定义类型转换规则
        // 目前使用通用的类型推断
        return $this->convertArrayTypes($response);
    }

    /**
     * 转换数组中的数据类型
     * 
     * @param array $data 数据数组
     * @return array 转换后的数组
     */
    private function convertArrayTypes(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->convertArrayTypes($value);
            } elseif (is_string($value)) {
                // 尝试转换数字字符串
                if (is_numeric($value)) {
                    if (strpos($value, '.') !== false) {
                        $data[$key] = (float) $value;
                    } else {
                        $data[$key] = (int) $value;
                    }
                }
                // 尝试转换布尔字符串
                elseif (in_array(strtolower($value), ['true', 'false'])) {
                    $data[$key] = strtolower($value) === 'true';
                }
            }
        }

        return $data;
    }

    /**
     * 检查是否有自定义适配器
     * 
     * @param string $method API方法名
     * @param string $fromVersion 源版本
     * @param string $toVersion 目标版本
     * @return bool
     */
    private function hasCustomAdapter(string $method, string $fromVersion, string $toVersion): bool
    {
        $key = "{$method}_{$fromVersion}_{$toVersion}";
        return isset($this->customAdapters[$key]);
    }

    /**
     * 应用自定义适配器
     * 
     * @param array $data 数据
     * @param string $method API方法名
     * @param string $fromVersion 源版本
     * @param string $toVersion 目标版本
     * @return array 适配后的数据
     */
    private function applyCustomAdapter(array $data, string $method, string $fromVersion, string $toVersion): array
    {
        $key = "{$method}_{$fromVersion}_{$toVersion}";
        $adapter = $this->customAdapters[$key];
        
        return $adapter($data, $fromVersion, $toVersion, $method);
    }

    /**
     * 更新方法统计信息
     * 
     * @param string $method API方法名
     * @param string $type 类型
     * @return void
     */
    private function updateMethodStats(string $method, string $type): void
    {
        if (!isset($this->adaptationStats['method_stats'][$method])) {
            $this->adaptationStats['method_stats'][$method] = [
                'request' => 0,
                'response' => 0
            ];
        }

        $this->adaptationStats['method_stats'][$method][$type]++;
    }

    /**
     * 获取类型的默认值
     * 
     * @param string $type 数据类型
     * @return mixed 默认值
     */
    private function getDefaultValueForType(string $type)
    {
        switch ($type) {
            case 'string':
                return '';
            case 'integer':
            case 'int':
                return 0;
            case 'float':
            case 'double':
                return 0.0;
            case 'boolean':
            case 'bool':
                return false;
            case 'array':
                return [];
            case 'object':
                return new \stdClass();
            default:
                return null;
        }
    }
}