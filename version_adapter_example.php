<?php

declare(strict_types=1);

/**
 * 版本适配器独立测试示例
 * 
 * 演示版本适配器的六项核心功能：
 * 1. 请求参数格式转换
 * 2. 响应数据格式统一
 * 3. 字段名称映射
 * 4. 数据类型转换
 * 5. 默认值处理
 * 6. 向下兼容保证
 */

// 简单的日志接口
interface SimpleLoggerInterface
{
    public function info(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;
    public function warning(string $message, array $context = []): void;
    public function debug(string $message, array $context = []): void;
    public function log(string $level, string $message, array $context = []): void;
}

// 简单的配置接口
interface SimpleConfigInterface
{
    public function get(string $key, $default = null);
    public function set(string $key, $value): void;
    public function has(string $key): bool;
    public function all(): array;
}

// 简单的版本适配器接口
interface SimpleVersionAdapterInterface
{
    public function adaptRequestParams(array $params, string $fromVersion, string $toVersion, string $method): array;
    public function adaptResponseData(array $response, string $fromVersion, string $toVersion, string $method): array;
    public function getFieldMapping(string $fromVersion, string $toVersion, string $method, string $type): array;
    public function convertDataType($value, string $fromType, string $toType);
    public function applyDefaultValues(array $data, string $version, string $method, string $type): array;
    public function isCompatible(string $fromVersion, string $toVersion, string $method): bool;
    public function getSupportedVersions(): array;
    public function registerCustomAdapter(string $method, string $fromVersion, string $toVersion, callable $adapter): void;
}

// 简化的版本适配器实现
class SimpleVersionAdapter implements SimpleVersionAdapterInterface
{
    public const SUPPORTED_VERSIONS = ['v1', 'v2'];
    public const TYPE_REQUEST = 'request';
    public const TYPE_RESPONSE = 'response';

    private SimpleConfigInterface $config;
    private SimpleLoggerInterface $logger;
    private array $fieldMappings = [];
    private array $defaultValues = [];
    private array $customAdapters = [];
    private array $compatibilityMatrix = [];
    private array $adaptationStats = [
        'total_adaptations' => 0,
        'successful_adaptations' => 0,
        'failed_adaptations' => 0,
        'method_stats' => []
    ];

    public function __construct(SimpleConfigInterface $config, SimpleLoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->initializeFieldMappings();
        $this->initializeDefaultValues();
        $this->initializeCompatibilityMatrix();
    }

    public function adaptRequestParams(array $params, string $fromVersion, string $toVersion, string $method): array
    {
        $this->validateVersions($fromVersion, $toVersion);
        $this->adaptationStats['total_adaptations']++;
        $this->updateMethodStats($method, 'request');

        try {
            // 检查自定义适配器
            if ($this->hasCustomAdapter($method, $fromVersion, $toVersion)) {
                $result = $this->applyCustomAdapter($params, $method, $fromVersion, $toVersion);
                $this->adaptationStats['successful_adaptations']++;
                return $result;
            }

            // 应用字段映射
            $mappedParams = $this->applyFieldMapping($params, $fromVersion, $toVersion, $method, self::TYPE_REQUEST);
            
            // 转换数据类型
            $convertedParams = $this->convertArrayTypes($mappedParams);
            
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
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function adaptResponseData(array $response, string $fromVersion, string $toVersion, string $method): array
    {
        $this->validateVersions($fromVersion, $toVersion);
        $this->adaptationStats['total_adaptations']++;
        $this->updateMethodStats($method, 'response');

        try {
            // 检查自定义适配器
            if ($this->hasCustomAdapter($method, $fromVersion, $toVersion)) {
                $result = $this->applyCustomAdapter($response, $method, $fromVersion, $toVersion);
                $this->adaptationStats['successful_adaptations']++;
                return $result;
            }

            // 应用字段映射
            $mappedResponse = $this->applyFieldMapping($response, $fromVersion, $toVersion, $method, self::TYPE_RESPONSE);
            
            // 转换数据类型
            $convertedResponse = $this->convertArrayTypes($mappedResponse);
            
            // 应用默认值
            $finalResponse = $this->applyDefaultValues($convertedResponse, $toVersion, $method, self::TYPE_RESPONSE);

            $this->adaptationStats['successful_adaptations']++;
            
            $this->logger->info('Response data adapted successfully', [
                'method' => $method,
                'from_version' => $fromVersion,
                'to_version' => $toVersion
            ]);

            return $finalResponse;

        } catch (\Exception $e) {
            $this->adaptationStats['failed_adaptations']++;
            $this->logger->error('Failed to adapt response data', [
                'method' => $method,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getFieldMapping(string $fromVersion, string $toVersion, string $method, string $type): array
    {
        $key = "{$fromVersion}_{$toVersion}_{$method}_{$type}";
        return $this->fieldMappings[$key] ?? [];
    }

    public function convertDataType($value, string $fromType, string $toType)
    {
        if ($fromType === $toType) {
            return $value;
        }

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

    public function isCompatible(string $fromVersion, string $toVersion, string $method): bool
    {
        $key = "{$fromVersion}_{$toVersion}_{$method}";
        return $this->compatibilityMatrix[$key] ?? false;
    }

    public function getSupportedVersions(): array
    {
        return self::SUPPORTED_VERSIONS;
    }

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

    public function getAdaptationStats(): array
    {
        return $this->adaptationStats;
    }

    private function initializeFieldMappings(): void
    {
        $this->fieldMappings = [
            // V1 到 V2 的字段映射
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
            'v1_v2_department.list_request' => [
                'id' => 'dept_id',
                'fetch_child' => 'include_children'
            ],
            'v1_v2_department.list_response' => [
                'id' => 'dept_id',
                'name' => 'dept_name',
                'parentid' => 'parent_id'
            ],
            'v1_v2_message.send_request' => [
                'touser' => 'user_id_list',
                'toparty' => 'dept_id_list',
                'msgtype' => 'msg_type'
            ],
            'v1_v2_message.send_response' => [
                'task_id' => 'message_id'
            ]
        ];

        // V2 到 V1 的反向映射
        foreach ($this->fieldMappings as $key => $mapping) {
            if (strpos($key, 'v1_v2_') === 0) {
                $reverseKey = str_replace('v1_v2_', 'v2_v1_', $key);
                $this->fieldMappings[$reverseKey] = array_flip($mapping);
            }
        }
    }

    private function initializeDefaultValues(): void
    {
        $this->defaultValues = [
            'v1_user.get_request' => [
                'lang' => 'zh_CN'
            ],
            'v1_message.send_request' => [
                'safe' => 0
            ],
            'v2_user.get_request' => [
                'language' => 'zh_CN'
            ],
            'v2_message.send_request' => [
                'enable_duplicate_check' => false,
                'duplicate_check_interval' => 1800
            ]
        ];
    }

    private function initializeCompatibilityMatrix(): void
    {
        $this->compatibilityMatrix = [
            'v1_v2_user.get' => true,
            'v1_v2_user.create' => true,
            'v1_v2_user.update' => true,
            'v1_v2_user.delete' => true,
            'v1_v2_department.list' => true,
            'v1_v2_department.get' => true,
            'v1_v2_department.create' => true,
            'v1_v2_department.update' => true,
            'v1_v2_message.send' => true,
            'v1_v2_message.recall' => false,
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

    private function validateVersions(string $fromVersion, string $toVersion): void
    {
        if (!in_array($fromVersion, self::SUPPORTED_VERSIONS)) {
            throw new \InvalidArgumentException("Unsupported source version: {$fromVersion}");
        }

        if (!in_array($toVersion, self::SUPPORTED_VERSIONS)) {
            throw new \InvalidArgumentException("Unsupported target version: {$toVersion}");
        }
    }

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

    private function convertArrayTypes(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->convertArrayTypes($value);
            } elseif (is_string($value)) {
                if (is_numeric($value)) {
                    if (strpos($value, '.') !== false) {
                        $data[$key] = (float) $value;
                    } else {
                        $data[$key] = (int) $value;
                    }
                } elseif (in_array(strtolower($value), ['true', 'false'])) {
                    $data[$key] = strtolower($value) === 'true';
                }
            }
        }

        return $data;
    }

    private function hasCustomAdapter(string $method, string $fromVersion, string $toVersion): bool
    {
        $key = "{$method}_{$fromVersion}_{$toVersion}";
        return isset($this->customAdapters[$key]);
    }

    private function applyCustomAdapter(array $data, string $method, string $fromVersion, string $toVersion): array
    {
        $key = "{$method}_{$fromVersion}_{$toVersion}";
        $adapter = $this->customAdapters[$key];
        
        return $adapter($data, $fromVersion, $toVersion, $method);
    }

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

// 模拟配置类
class SimpleConfig implements SimpleConfigInterface
{
    private array $config = [];

    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $this->config[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($this->config[$key]);
    }

    public function all(): array
    {
        return $this->config;
    }
}

// 模拟日志类
class SimpleLogger implements SimpleLoggerInterface
{
    private array $logs = [];

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $this->logs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        echo "[{$level}] {$message}\n";
    }

    public function getLogs(): array
    {
        return $this->logs;
    }
}

// ===== 测试开始 =====

$config = new SimpleConfig();
$logger = new SimpleLogger();
$adapter = new SimpleVersionAdapter($config, $logger);

echo "=== 版本适配器 (VersionAdapter) 功能测试 ===\n\n";

// 1. 测试请求参数格式转换
echo "1. 测试请求参数格式转换\n";
echo "-----------------------------------\n";

$v1Params = [
    'userid' => 'user123',
    'lang' => 'en_US',
    'department' => [1, 2, 3]
];

echo "V1 请求参数: " . json_encode($v1Params, JSON_UNESCAPED_UNICODE) . "\n";

try {
    $v2Params = $adapter->adaptRequestParams($v1Params, 'v1', 'v2', 'user.get');
    echo "转换为 V2 参数: " . json_encode($v2Params, JSON_UNESCAPED_UNICODE) . "\n";
} catch (Exception $e) {
    echo "转换失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 2. 测试响应数据格式统一
echo "2. 测试响应数据格式统一\n";
echo "-----------------------------------\n";

$v1Response = [
    'userid' => 'user123',
    'name' => '张三',
    'mobile' => '13800138000',
    'department' => [1, 2]
];

echo "V1 响应数据: " . json_encode($v1Response, JSON_UNESCAPED_UNICODE) . "\n";

try {
    $v2Response = $adapter->adaptResponseData($v1Response, 'v1', 'v2', 'user.get');
    echo "转换为 V2 响应: " . json_encode($v2Response, JSON_UNESCAPED_UNICODE) . "\n";
} catch (Exception $e) {
    echo "转换失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. 测试字段名称映射
echo "3. 测试字段名称映射\n";
echo "-----------------------------------\n";

$requestMapping = $adapter->getFieldMapping('v1', 'v2', 'user.get', 'request');
echo "V1->V2 请求字段映射: " . json_encode($requestMapping, JSON_UNESCAPED_UNICODE) . "\n";

$responseMapping = $adapter->getFieldMapping('v1', 'v2', 'user.get', 'response');
echo "V1->V2 响应字段映射: " . json_encode($responseMapping, JSON_UNESCAPED_UNICODE) . "\n";

echo "\n";

// 4. 测试数据类型转换
echo "4. 测试数据类型转换\n";
echo "-----------------------------------\n";

$testValues = [
    ['123', 'string', 'integer'],
    ['123.45', 'string', 'float'],
    ['true', 'string', 'boolean'],
    [1, 'integer', 'string'],
    [null, 'null', 'string']
];

foreach ($testValues as [$value, $fromType, $toType]) {
    $converted = $adapter->convertDataType($value, $fromType, $toType);
    echo "转换 '{$value}' 从 {$fromType} 到 {$toType}: " . var_export($converted, true) . "\n";
}

echo "\n";

// 5. 测试默认值处理
echo "5. 测试默认值处理\n";
echo "-----------------------------------\n";

$incompleteData = [
    'userid' => 'user123'
];

echo "原始数据: " . json_encode($incompleteData, JSON_UNESCAPED_UNICODE) . "\n";

$dataWithDefaults = $adapter->applyDefaultValues($incompleteData, 'v1', 'user.get', 'request');
echo "应用默认值后: " . json_encode($dataWithDefaults, JSON_UNESCAPED_UNICODE) . "\n";

echo "\n";

// 6. 测试向下兼容保证
echo "6. 测试向下兼容保证\n";
echo "-----------------------------------\n";

$compatibilityTests = [
    ['v1', 'v2', 'user.get'],
    ['v1', 'v2', 'user.create'],
    ['v1', 'v2', 'message.recall'],
    ['v2', 'v1', 'user.get']
];

foreach ($compatibilityTests as [$from, $to, $method]) {
    $isCompatible = $adapter->isCompatible($from, $to, $method);
    $status = $isCompatible ? '✓ 兼容' : '✗ 不兼容';
    echo "{$from} -> {$to} ({$method}): {$status}\n";
}

echo "\n";

// 7. 测试自定义适配器注册
echo "7. 测试自定义适配器注册\n";
echo "-----------------------------------\n";

$adapter->registerCustomAdapter('custom.method', 'v1', 'v2', function($data, $fromVersion, $toVersion, $method) {
    echo "执行自定义适配器: {$method} ({$fromVersion} -> {$toVersion})\n";
    $data['custom_field'] = 'added_by_custom_adapter';
    return $data;
});

$customData = ['original' => 'data'];
echo "原始数据: " . json_encode($customData, JSON_UNESCAPED_UNICODE) . "\n";

try {
    $adaptedData = $adapter->adaptRequestParams($customData, 'v1', 'v2', 'custom.method');
    echo "自定义适配后: " . json_encode($adaptedData, JSON_UNESCAPED_UNICODE) . "\n";
} catch (Exception $e) {
    echo "自定义适配失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 8. 测试支持的版本列表
echo "8. 测试支持的版本列表\n";
echo "-----------------------------------\n";

$supportedVersions = $adapter->getSupportedVersions();
echo "支持的版本: " . implode(', ', $supportedVersions) . "\n";

echo "\n";

// 9. 测试适配统计信息
echo "9. 测试适配统计信息\n";
echo "-----------------------------------\n";

$stats = $adapter->getAdaptationStats();
echo "适配统计信息:\n";
echo "- 总适配次数: " . $stats['total_adaptations'] . "\n";
echo "- 成功次数: " . $stats['successful_adaptations'] . "\n";
echo "- 失败次数: " . $stats['failed_adaptations'] . "\n";
echo "- 方法统计: " . json_encode($stats['method_stats'], JSON_UNESCAPED_UNICODE) . "\n";

echo "\n";

// 10. 测试错误处理
echo "10. 测试错误处理\n";
echo "-----------------------------------\n";

try {
    $adapter->adaptRequestParams([], 'v3', 'v2', 'test.method');
} catch (Exception $e) {
    echo "预期错误 (不支持的版本): " . $e->getMessage() . "\n";
}

try {
    $adapter->adaptRequestParams([], 'v1', 'v3', 'test.method');
} catch (Exception $e) {
    echo "预期错误 (不支持的目标版本): " . $e->getMessage() . "\n";
}

echo "\n=== 版本适配器测试完成 ===\n";

echo "\n=== 日志记录 ===\n";
$logs = $logger->getLogs();
echo "共记录 " . count($logs) . " 条日志\n";