<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DingTalk\Http\HttpClient;
use DingTalk\Http\Middleware\HttpMiddleware;
use DingTalk\Config\ConfigManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

// 创建配置管理器
$config = new ConfigManager([
    'http' => [
        'timeout' => 30,
        'connect_timeout' => 10,
        'retries' => 3,
        'retry_delay' => 1000,
        'pool' => [
            'concurrency' => 10,
            'max_connections' => 100,
        ],
    ],
]);

// 创建HTTP客户端
$httpClient = new HttpClient($config);

echo "=== HTTP客户端使用示例 ===\n\n";

// 1. 基本GET请求
echo "1. 基本GET请求:\n";
try {
    $response = $httpClient->get('https://httpbin.org/get?param1=value1&param2=value2');
    echo "状态码: " . $httpClient->getLastStatusCode() . "\n";
    echo "响应: " . substr(json_encode($response), 0, 200) . "...\n\n";
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n\n";
}

// 2. POST请求
echo "2. POST请求:\n";
try {
    $response = $httpClient->post('https://httpbin.org/post', [
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ]);
    echo "状态码: " . $httpClient->getLastStatusCode() . "\n";
    echo "响应: " . substr(json_encode($response), 0, 200) . "...\n\n";
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n\n";
}

// 3. 设置超时
echo "3. 设置超时:\n";
$httpClient->setTimeout(5);
$httpClient->setConnectTimeout(3);
echo "已设置超时时间为5秒，连接超时为3秒\n\n";

// 4. 设置重试
echo "4. 设置重试:\n";
$httpClient->setRetries(5);
$httpClient->setRetryDelay(2000);
echo "已设置重试次数为5次，重试延迟为2秒\n\n";

// 5. 添加中间件
echo "5. 添加中间件:\n";

// 添加认证中间件
$httpClient->addMiddleware('auth', HttpMiddleware::auth('your-token-here'));
echo "已添加认证中间件\n";

// 添加请求头中间件
$httpClient->addMiddleware('headers', HttpMiddleware::addHeaders([
    'X-Custom-Header' => 'CustomValue',
    'X-API-Version' => 'v1.0'
]));
echo "已添加自定义请求头中间件\n";

// 添加时间统计中间件
$logger = new NullLogger(); // 创建一个空日志记录器用于演示
$httpClient->addMiddleware('timing', HttpMiddleware::timing($logger));
echo "已添加时间统计中间件\n\n";

// 6. 使用中间件的请求
echo "6. 使用中间件的请求:\n";
try {
    $response = $httpClient->get('https://httpbin.org/headers');
    echo "状态码: " . $httpClient->getLastStatusCode() . "\n";
    $headers = $httpClient->getLastResponseHeaders();
    echo "响应头数量: " . count($headers) . "\n\n";
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n\n";
}

// 7. 批量请求
echo "7. 批量请求:\n";
$requests = [
    ['method' => 'GET', 'url' => 'https://httpbin.org/get?id=1'],
    ['method' => 'GET', 'url' => 'https://httpbin.org/get?id=2'],
    ['method' => 'GET', 'url' => 'https://httpbin.org/get?id=3'],
];

try {
    $responses = $httpClient->batchRequest($requests);
    echo "批量请求完成，共 " . count($responses) . " 个响应\n";
    foreach ($responses as $index => $response) {
        if ($response instanceof Exception) {
            echo "请求 $index 失败: " . $response->getMessage() . "\n";
        } else {
            echo "请求 $index 成功，响应长度: " . strlen($response) . "\n";
        }
    }
    echo "\n";
} catch (Exception $e) {
    echo "批量请求错误: " . $e->getMessage() . "\n\n";
}

// 8. 文件上传
echo "8. 文件上传示例:\n";
// 创建一个临时文件用于演示
$tempFile = tempnam(sys_get_temp_dir(), 'upload_test');
file_put_contents($tempFile, 'This is a test file content for upload.');

try {
    $response = $httpClient->upload('https://httpbin.org/post', [
        'file' => $tempFile,
        'description' => 'Test file upload'
    ]);
    echo "文件上传成功\n";
    echo "状态码: " . $httpClient->getLastStatusCode() . "\n\n";
} catch (Exception $e) {
    echo "文件上传错误: " . $e->getMessage() . "\n\n";
} finally {
    // 清理临时文件
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
}

// 9. 设置连接池配置
echo "9. 设置连接池配置:\n";
$httpClient->setPoolConfig([
    'concurrency' => 20,
    'max_connections' => 200
]);
echo "已设置连接池并发数为20，最大连接数为200\n\n";

// 10. 移除中间件
echo "10. 移除中间件:\n";
$httpClient->removeMiddleware('auth');
echo "已移除认证中间件\n";

// 11. 不同HTTP方法示例
echo "11. 不同HTTP方法示例:\n";

// PUT请求
try {
    $response = $httpClient->put('https://httpbin.org/put', ['data' => 'updated']);
    echo "PUT请求状态码: " . $httpClient->getLastStatusCode() . "\n";
} catch (Exception $e) {
    echo "PUT请求错误: " . $e->getMessage() . "\n";
}

// DELETE请求
try {
    $response = $httpClient->delete('https://httpbin.org/delete');
    echo "DELETE请求状态码: " . $httpClient->getLastStatusCode() . "\n";
} catch (Exception $e) {
    echo "DELETE请求错误: " . $e->getMessage() . "\n";
}

// PATCH请求
try {
    $response = $httpClient->patch('https://httpbin.org/patch', ['field' => 'patched']);
    echo "PATCH请求状态码: " . $httpClient->getLastStatusCode() . "\n";
} catch (Exception $e) {
    echo "PATCH请求错误: " . $e->getMessage() . "\n";
}

echo "\n=== HTTP客户端示例完成 ===\n";