<?php

/**
 * V1 API适配器使用示例
 * 
 * 本示例展示如何使用V1ApiAdapter来调用钉钉旧版V1 API
 */

require_once __DIR__ . '/../vendor/autoload.php';

use DingTalk\Config\Config;
use DingTalk\Http\GuzzleHttpClient;
use DingTalk\Auth\AuthManager;
use DingTalk\Version\V1ApiAdapter;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

try {
    // 1. 初始化配置
    $config = new Config([
        'app_key' => 'your_app_key',
        'app_secret' => 'your_app_secret',
        'v1_api_base_url' => 'https://oapi.dingtalk.com',
        'v1_api_timeout' => 30,
        'sdk_version' => '1.0.0'
    ]);

    // 2. 初始化HTTP客户端
    $httpClient = new GuzzleHttpClient();

    // 3. 初始化认证管理器
    $authManager = new AuthManager($config, $httpClient);

    // 4. 初始化日志记录器
    $logger = new Logger('v1_api');
    $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

    // 5. 创建V1 API适配器
    $v1Adapter = new V1ApiAdapter($config, $httpClient, $authManager, $logger);

    echo "=== V1 API适配器使用示例 ===\n\n";

    // 示例1: 获取用户信息
    echo "1. 获取用户信息:\n";
    try {
        $userInfo = $v1Adapter->request('user.get', [
            'user_id' => 'test_user_id'  // 新格式参数，会自动转换为V1的userid
        ]);
        
        echo "用户ID: " . ($userInfo['user_id'] ?? 'N/A') . "\n";
        echo "用户名: " . ($userInfo['username'] ?? 'N/A') . "\n";
        echo "部门列表: " . json_encode($userInfo['dept_list'] ?? []) . "\n";
    } catch (Exception $e) {
        echo "错误: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 示例2: 获取部门列表
    echo "2. 获取部门列表:\n";
    try {
        $deptList = $v1Adapter->request('department.list', [
            'dept_id' => 1,              // 新格式参数，会自动转换为V1的id
            'include_child' => true      // 新格式参数，会自动转换为V1的fetch_child
        ]);
        
        echo "部门数量: " . count($deptList['department'] ?? []) . "\n";
    } catch (Exception $e) {
        echo "错误: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 示例3: 发送消息
    echo "3. 发送消息:\n";
    try {
        $result = $v1Adapter->request('message.send', [
            'user_list' => ['user1', 'user2'],  // 新格式参数，会自动转换为V1的touser
            'msg_type' => 'text',               // 新格式参数，会自动转换为V1的msgtype
            'content' => 'Hello from V1 API!'
        ]);
        
        echo "消息发送结果: " . ($result['errcode'] === 0 ? '成功' : '失败') . "\n";
    } catch (Exception $e) {
        echo "错误: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 示例4: 使用GET方法
    echo "4. 使用GET方法获取数据:\n";
    try {
        $result = $v1Adapter->request('user.get', [
            'user_id' => 'test_user_id'
        ], 'GET');  // 指定HTTP方法为GET
        
        echo "GET请求结果: " . ($result['errcode'] === 0 ? '成功' : '失败') . "\n";
    } catch (Exception $e) {
        echo "错误: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 示例5: 查看请求统计
    echo "5. 请求统计信息:\n";
    $stats = $v1Adapter->getRequestStats();
    echo "总请求数: " . $stats['total_requests'] . "\n";
    echo "成功请求数: " . $stats['successful_requests'] . "\n";
    echo "失败请求数: " . $stats['failed_requests'] . "\n";
    echo "认证失败数: " . $stats['auth_failed_requests'] . "\n";
    echo "限流请求数: " . $stats['rate_limited_requests'] . "\n";
    echo "\n";

    // 示例6: 参数适配演示
    echo "6. 参数适配演示:\n";
    $originalParams = [
        'user_id' => 'test_user',
        'language' => 'zh_CN',
        'include_child' => true
    ];
    
    $adaptedParams = $v1Adapter->adaptRequestParams('user.get', $originalParams);
    echo "原始参数: " . json_encode($originalParams) . "\n";
    echo "适配后参数: " . json_encode($adaptedParams) . "\n";
    echo "\n";

    // 示例7: 响应适配演示
    echo "7. 响应适配演示:\n";
    $v1Response = [
        'errcode' => 0,
        'errmsg' => 'ok',
        'userid' => 'test_user',
        'name' => 'Test User',
        'department' => [1, 2]
    ];
    
    $adaptedResponse = $v1Adapter->adaptResponseData('user.get', $v1Response);
    echo "V1响应: " . json_encode($v1Response) . "\n";
    echo "适配后响应: " . json_encode($adaptedResponse) . "\n";

} catch (Exception $e) {
    echo "初始化错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪: " . $e->getTraceAsString() . "\n";
}

echo "\n=== 示例结束 ===\n";