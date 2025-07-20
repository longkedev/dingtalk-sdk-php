<?php

require_once __DIR__ . '/vendor/autoload.php';

use DingTalk\DingTalk;
use DingTalk\Config\ConfigManager;

// 示例：钉钉PHP SDK使用指南

echo "=== 钉钉PHP SDK使用示例 ===\n\n";

try {
    // 1. 初始化配置
    echo "1. 初始化配置...\n";
    $config = new ConfigManager([
        'app_key' => 'your_app_key',
        'app_secret' => 'your_app_secret',
        'agent_id' => 'your_agent_id',
        'api_version' => 'v1', // 或 'v2'
        'base_url' => 'https://oapi.dingtalk.com',
        'timeout' => 30,
        'cache' => [
            'driver' => 'file',
            'path' => __DIR__ . '/cache',
            'prefix' => 'dingtalk_',
            'default_ttl' => 3600,
        ],
        'log' => [
            'enabled' => true,
            'level' => 'info',
            'handlers' => [
                [
                    'type' => 'file',
                    'path' => __DIR__ . '/logs/dingtalk.log',
                ],
                [
                    'type' => 'console',
                    'use_colors' => true,
                ],
            ],
        ],
    ]);

    // 2. 创建钉钉客户端
    echo "2. 创建钉钉客户端...\n";
    $dingtalk = new DingTalk($config);

    // 3. 用户管理示例
    echo "\n3. 用户管理示例:\n";
    
    // 获取用户详情
    echo "   - 获取用户详情\n";
    $user = $dingtalk->user()->get('user123');
    echo "     用户信息: " . json_encode($user, JSON_UNESCAPED_UNICODE) . "\n";

    // 通过手机号获取用户
    echo "   - 通过手机号获取用户\n";
    $userByMobile = $dingtalk->user()->getByMobile('13800138000');
    echo "     用户ID: " . ($userByMobile['userid'] ?? 'N/A') . "\n";

    // 获取部门用户列表
    echo "   - 获取部门用户列表\n";
    $departmentUsers = $dingtalk->user()->listByDepartment(1, 0, 20);
    echo "     部门用户数量: " . count($departmentUsers['userlist'] ?? []) . "\n";

    // 4. 部门管理示例
    echo "\n4. 部门管理示例:\n";
    
    // 获取部门详情
    echo "   - 获取部门详情\n";
    $department = $dingtalk->department()->get(1);
    echo "     部门名称: " . ($department['name'] ?? 'N/A') . "\n";

    // 获取子部门列表
    echo "   - 获取子部门列表\n";
    $subDepartments = $dingtalk->department()->listSubDepartments(1);
    echo "     子部门数量: " . count($subDepartments['dept_id_list'] ?? []) . "\n";

    // 5. 消息推送示例
    echo "\n5. 消息推送示例:\n";
    
    // 发送文本消息
    echo "   - 发送工作通知\n";
    $textMessage = $dingtalk->message()->createTextMessage('这是一条测试消息');
    $result = $dingtalk->message()->sendWorkNotification([
        'agent_id' => $config->get('agent_id'),
        'userid_list' => 'user123,user456',
        'msg' => $textMessage,
    ]);
    echo "     消息发送结果: " . ($result['task_id'] ?? 'Failed') . "\n";

    // 发送链接消息
    echo "   - 发送链接消息\n";
    $linkMessage = $dingtalk->message()->createLinkMessage(
        '钉钉开放平台',
        '钉钉开放平台是企业数字化转型的重要工具',
        'https://open.dingtalk.com',
        'https://img.alicdn.com/tfs/TB1NwmBEL9TBuNjy1zbXXXpepXa-2400-1218.png'
    );
    
    // 发送Markdown消息
    echo "   - 发送Markdown消息\n";
    $markdownMessage = $dingtalk->message()->createMarkdownMessage(
        '项目更新通知',
        "## 项目进度更新\n\n**当前进度:** 80%\n\n**完成功能:**\n- 用户管理\n- 部门管理\n- 消息推送\n\n**下一步计划:**\n- 考勤管理\n- 审批流程"
    );

    // 6. 媒体文件管理示例
    echo "\n6. 媒体文件管理示例:\n";
    
    // 上传图片（示例，需要实际文件）
    echo "   - 上传媒体文件\n";
    // $uploadResult = $dingtalk->media()->uploadImage('/path/to/image.jpg');
    // echo "     媒体ID: " . ($uploadResult['media_id'] ?? 'N/A') . "\n";

    // 7. 考勤管理示例
    echo "\n7. 考勤管理示例:\n";
    
    // 获取考勤记录
    echo "   - 获取考勤记录\n";
    $attendanceRecords = $dingtalk->attendance()->getUserRecords(
        ['user123', 'user456'],
        '2024-01-01',
        '2024-01-31'
    );
    echo "     考勤记录数量: " . count($attendanceRecords['recordresult'] ?? []) . "\n";

    // 获取考勤组信息
    echo "   - 获取考勤组信息\n";
    $attendanceGroups = $dingtalk->attendance()->getGroups();
    echo "     考勤组数量: " . count($attendanceGroups['result'] ?? []) . "\n";

    // 8. 批量操作示例
    echo "\n8. 批量操作示例:\n";
    
    // 批量获取用户信息
    echo "   - 批量获取用户信息\n";
    $userIds = ['user123', 'user456', 'user789'];
    $batchUsers = $dingtalk->user()->batchGet($userIds);
    echo "     批量获取用户数量: " . count($batchUsers) . "\n";

    // 9. 缓存使用示例
    echo "\n9. 缓存使用示例:\n";
    
    // 手动缓存操作
    echo "   - 设置缓存\n";
    $dingtalk->cache()->set('test_key', 'test_value', 3600);
    
    echo "   - 获取缓存\n";
    $cachedValue = $dingtalk->cache()->get('test_key');
    echo "     缓存值: " . $cachedValue . "\n";

    // 10. 错误处理示例
    echo "\n10. 错误处理示例:\n";
    
    try {
        // 尝试获取不存在的用户
        $dingtalk->user()->get('nonexistent_user');
    } catch (\DingTalk\Exceptions\ApiException $e) {
        echo "   - API异常: " . $e->getMessage() . "\n";
        echo "     错误代码: " . $e->getErrorCode() . "\n";
        echo "     请求ID: " . $e->getRequestId() . "\n";
    } catch (\DingTalk\Exceptions\DingTalkException $e) {
        echo "   - 钉钉异常: " . $e->getMessage() . "\n";
    }

    echo "\n=== 示例执行完成 ===\n";

} catch (\Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}

// 高级用法示例
echo "\n=== 高级用法示例 ===\n";

// 自定义配置
$customConfig = new ConfigManager();
$customConfig->set('app_key', 'custom_app_key');
$customConfig->set('timeout', 60);

// 使用自定义缓存驱动
$customConfig->set('cache.driver', 'memory');

// 自定义日志配置
$customConfig->set('log.handlers', [
    [
        'type' => 'file',
        'path' => '/custom/log/path.log',
        'level' => 'debug',
    ],
]);

// 创建自定义客户端
$customDingTalk = new DingTalk($customConfig);

// 链式调用示例
echo "链式调用示例:\n";
try {
    $result = $customDingTalk
        ->user()
        ->get('user123');
    
    echo "用户名: " . ($result['name'] ?? 'N/A') . "\n";
} catch (\Exception $e) {
    echo "链式调用错误: " . $e->getMessage() . "\n";
}

// 服务容器使用示例
echo "\n服务容器使用示例:\n";
$container = $dingtalk->getContainer();

// 获取服务
$httpClient = $container->get('http');
$logger = $container->get('logger');
$cache = $container->get('cache');

echo "HTTP客户端类型: " . get_class($httpClient) . "\n";
echo "日志管理器类型: " . get_class($logger) . "\n";
echo "缓存管理器类型: " . get_class($cache) . "\n";

echo "\n=== 所有示例执行完成 ===\n";