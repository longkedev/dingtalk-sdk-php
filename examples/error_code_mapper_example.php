<?php
/**
 * 错误码映射器使用示例
 * 
 * 本示例演示了如何使用ErrorCodeMapper进行错误处理
 */

// 先加载基础异常类
require_once __DIR__ . '/../src/Exceptions/DingTalkException.php';

// 再加载具体异常类
require_once __DIR__ . '/../src/Exceptions/AuthException.php';
require_once __DIR__ . '/../src/Exceptions/ApiException.php';
require_once __DIR__ . '/../src/Exceptions/RateLimitException.php';
require_once __DIR__ . '/../src/Exceptions/NetworkException.php';
require_once __DIR__ . '/../src/Exceptions/ConfigException.php';
require_once __DIR__ . '/../src/Exceptions/ValidationException.php';
require_once __DIR__ . '/../src/Exceptions/ContainerException.php';

// 最后加载映射器相关类
require_once __DIR__ . '/../src/Exceptions/ErrorCodes.php';
require_once __DIR__ . '/../src/Exceptions/ErrorMessageTranslator.php';
require_once __DIR__ . '/../src/Exceptions/ErrorContextCollector.php';
require_once __DIR__ . '/../src/Exceptions/ErrorCodeMapper.php';

use DingTalk\Exceptions\ErrorCodeMapper;
use DingTalk\Exceptions\ErrorCodes;

echo "=== 错误码映射器使用示例 ===\n\n";

// 1. 创建错误码映射器实例
echo "--- 1. 创建错误码映射器 ---\n";
$mapper = new ErrorCodeMapper('zh', false); // 中文，不收集敏感信息
echo "✅ 错误码映射器创建成功\n\n";

// 2. 基本错误码映射
echo "--- 2. 基本错误码映射 ---\n";

// V2 API错误码
$v2Error = $mapper->mapErrorCode('40001', 'v2');
echo "V2错误码 40001:\n";
echo "  类型: {$v2Error['type']}\n";
echo "  消息: {$v2Error['message']}\n";
echo "  API版本: {$v2Error['api_version']}\n\n";

// V1 API错误码
$v1Error = $mapper->mapErrorCode('40014', 'v1');
echo "V1错误码 40014:\n";
echo "  类型: {$v1Error['type']}\n";
echo "  消息: {$v1Error['message']}\n";
echo "  API版本: {$v1Error['api_version']}\n\n";

// 自定义错误码
$customError = $mapper->mapErrorCode('SDK_001', 'custom');
echo "自定义错误码 SDK_001:\n";
echo "  类型: {$customError['type']}\n";
echo "  消息: {$customError['message']}\n\n";

// 3. 多语言支持
echo "--- 3. 多语言支持 ---\n";

$languages = ['zh', 'en', 'ja', 'ko'];
foreach ($languages as $lang) {
    $mapper->setLanguage($lang);
    $error = $mapper->mapErrorCode('40001', 'v2');
    echo "语言 {$lang}: {$error['message']}\n";
}
echo "\n";

// 4. 错误恢复建议
echo "--- 4. 错误恢复建议 ---\n";
$mapper->setLanguage('zh');

$suggestions = [
    ['40001', 'v2', '访问令牌无效'],
    ['90018', 'v2', '接口调用频率限制'],
    ['50001', 'v2', '网络连接错误']
];

foreach ($suggestions as [$code, $version, $desc]) {
    $suggestion = $mapper->getRecoverySuggestion($code, $version);
    echo "{$desc} ({$code}): {$suggestion}\n";
}
echo "\n";

// 5. 错误类型判断
echo "--- 5. 错误类型判断 ---\n";

$testCodes = [
    ['40001', 'v2', '认证错误'],
    ['40015', 'v2', 'API错误'],
    ['90018', 'v2', '限流错误'],
    ['50001', 'v2', '网络错误']
];

foreach ($testCodes as [$code, $version, $expected]) {
    $isAuth = $mapper->isAuthError($code, $version);
    $isApi = $mapper->isApiError($code, $version);
    $isRateLimit = $mapper->isRateLimitError($code, $version);
    $isNetwork = $mapper->isNetworkError($code, $version);
    
    $actualType = '';
    if ($isAuth) $actualType = '认证错误';
    elseif ($isApi) $actualType = 'API错误';
    elseif ($isRateLimit) $actualType = '限流错误';
    elseif ($isNetwork) $actualType = '网络错误';
    
    $status = ($actualType === $expected) ? '✅' : '❌';
    echo "{$status} 错误码 {$code}: 预期 {$expected}, 实际 {$actualType}\n";
}
echo "\n";

// 6. 上下文收集
echo "--- 6. 上下文收集 ---\n";

// 添加HTTP上下文
$mapper->addHttpContext([
    'method' => 'POST',
    'url' => 'https://api.dingtalk.com/v1.0/oauth2/accessToken',
    'headers' => ['Content-Type' => 'application/json'],
    'status_code' => 401
]);

// 添加API上下文
$mapper->addApiContext([
    'endpoint' => '/v1.0/oauth2/accessToken',
    'version' => 'v2',
    'method' => 'POST',
    'response_code' => 401
]);

// 添加认证上下文
$mapper->addAuthContext([
    'app_type' => 'internal',
    'corp_id' => 'demo_corp_id',
    'app_key' => 'demo_app_key'
]);

// 收集完整上下文
$context = $mapper->collectErrorContext('40001', [
    'custom_field' => 'custom_value',
    'timestamp' => date('Y-m-d H:i:s')
]);

echo "收集到的错误上下文:\n";
echo json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// 7. 异常实例创建
echo "--- 7. 异常实例创建 ---\n";

$exceptionTests = [
    ['40001', 'v2', 'AuthException'],
    ['40015', 'v2', 'ApiException'],
    ['90018', 'v2', 'RateLimitException'],
    ['50001', 'v2', 'NetworkException'],
    ['SDK_001', 'custom', 'ConfigException']
];

foreach ($exceptionTests as [$code, $version, $expectedClass]) {
    try {
        $exception = $mapper->createException($code, $version);
        $actualClass = (new ReflectionClass($exception))->getShortName();
        $status = ($actualClass === $expectedClass) ? '✅' : '❌';
        echo "{$status} 错误码 {$code}: 预期 {$expectedClass}, 实际 {$actualClass}\n";
    } catch (Exception $e) {
        echo "❌ 错误码 {$code}: 创建异常失败 - {$e->getMessage()}\n";
    }
}
echo "\n";

// 8. 完整错误处理流程演示
echo "--- 8. 完整错误处理流程演示 ---\n";

function simulateApiCall($errorCode, $apiVersion = 'v2') {
    global $mapper;
    
    echo "模拟API调用失败，错误码: {$errorCode}\n";
    
    // 1. 映射错误码
    $errorInfo = $mapper->mapErrorCode($errorCode, $apiVersion);
    echo "错误信息: {$errorInfo['message']}\n";
    
    // 2. 获取恢复建议
    $recovery = $mapper->getRecoverySuggestion($errorCode, $apiVersion);
    echo "恢复建议: {$recovery}\n";
    
    // 3. 创建具体异常
    $exception = $mapper->createException($errorCode, $apiVersion);
    echo "异常类型: " . get_class($exception) . "\n";
    
    // 4. 收集上下文（简化版）
    $context = $mapper->collectErrorContext($errorCode, [
        'simulation' => true,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    echo "上下文收集: " . count($context) . " 个字段\n";
    
    echo "---\n";
}

// 模拟不同类型的错误
simulateApiCall('40001', 'v2'); // 认证错误
simulateApiCall('90018', 'v2'); // 限流错误
simulateApiCall('SDK_002', 'custom'); // 自定义错误

// 9. ErrorCodes类使用示例
echo "--- 9. ErrorCodes类使用示例 ---\n";

$testErrorCodes = ['40001', '40015', '90018', '50001'];

foreach ($testErrorCodes as $code) {
    $apiVersion = ErrorCodes::getApiVersion($code);
    $errorType = ErrorCodes::getErrorType($code);
    
    echo "错误码 {$code}: API版本={$apiVersion}, 类型={$errorType}\n";
}
echo "\n";

// 10. 支持的错误码列表
echo "--- 10. 支持的错误码统计 ---\n";

$v2Codes = $mapper->getSupportedErrorCodes('v2');
$v1Codes = $mapper->getSupportedErrorCodes('v1');
$customCodes = $mapper->getSupportedErrorCodes('custom');

echo "V2 API错误码数量: " . count($v2Codes) . "\n";
echo "V1 API错误码数量: " . count($v1Codes) . "\n";
echo "自定义错误码数量: " . count($customCodes) . "\n";

$allCodes = $mapper->getSupportedErrorCodes('all');
echo "总错误码数量: " . count($allCodes) . "\n\n";

echo "=== 错误码映射器示例完成 ===\n";