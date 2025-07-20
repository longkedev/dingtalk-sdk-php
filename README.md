# 钉钉 PHP SDK

一个功能完整、易于使用的钉钉开放平台 PHP SDK，支持用户管理、部门管理、消息推送、媒体文件管理、考勤管理等核心功能。

## 特性

- 🚀 **简单易用** - 链式调用，直观的API设计
- 🔧 **功能完整** - 覆盖钉钉开放平台主要API
- 🎯 **类型安全** - 完整的类型提示和参数验证
- 💾 **智能缓存** - 内置缓存机制，提升性能
- 📝 **详细日志** - 完整的请求/响应日志记录
- 🔒 **安全可靠** - 自动token管理和签名验证
- 🧪 **测试覆盖** - 完整的单元测试和集成测试
- 📚 **文档完善** - 详细的使用文档和示例

## 环境要求

- PHP >= 8.0
- ext-json
- ext-curl
- ext-openssl

## 安装

使用 Composer 安装：

```bash
composer require your-vendor/dingtalk-sdk
```

## 快速开始

### 基础配置

```php
<?php

require_once 'vendor/autoload.php';

use DingTalk\DingTalk;
use DingTalk\Config\ConfigManager;

// 创建配置
$config = new ConfigManager([
    'app_key' => 'your_app_key',
    'app_secret' => 'your_app_secret',
    'agent_id' => 'your_agent_id',
    'api_version' => 'v1', // 或 'v2'
    'base_url' => 'https://oapi.dingtalk.com',
    'timeout' => 30,
    'cache' => [
        'driver' => 'memory', // 支持 memory, file, redis
        'prefix' => 'dingtalk_',
        'default_ttl' => 3600,
    ],
    'log' => [
        'enabled' => true,
        'level' => 'info',
        'handlers' => [
            [
                'type' => 'file',
                'path' => '/path/to/logs/dingtalk.log',
                'level' => 'info',
            ],
            [
                'type' => 'console',
                'level' => 'debug',
                'colored' => true,
            ],
        ],
    ],
]);

// 创建钉钉客户端
$dingtalk = new DingTalk($config);
```

### 用户管理

```php
// 获取用户详情
$userInfo = $dingtalk->user()->getUserInfo('user_id_123');

// 通过手机号获取用户ID
$userId = $dingtalk->user()->getUserIdByMobile('13800138000');

// 获取部门用户列表
$users = $dingtalk->user()->getDepartmentUsers(1, 0, 100);

// 批量获取用户详情
$userList = $dingtalk->user()->getUserInfoBatch(['user1', 'user2', 'user3']);

// 创建用户
$newUser = $dingtalk->user()->createUser([
    'name' => '张三',
    'mobile' => '13800138000',
    'department' => [1],
    'position' => '开发工程师',
    'email' => 'zhangsan@example.com',
]);

// 更新用户信息
$dingtalk->user()->updateUser('user_id_123', [
    'name' => '李四',
    'position' => '高级开发工程师',
]);

// 删除用户
$dingtalk->user()->deleteUser('user_id_123');
```

### 部门管理

```php
// 获取部门详情
$deptInfo = $dingtalk->department()->getDepartmentInfo(1);

// 获取子部门列表
$subDepts = $dingtalk->department()->getSubDepartments(1);

// 创建部门
$newDept = $dingtalk->department()->createDepartment([
    'name' => '技术部',
    'parentid' => 1,
    'order' => 1,
]);

// 更新部门
$dingtalk->department()->updateDepartment(2, [
    'name' => '研发部',
    'order' => 2,
]);

// 删除部门
$dingtalk->department()->deleteDepartment(2);

// 获取部门用户数量
$userCount = $dingtalk->department()->getDepartmentUserCount(1);
```

### 消息推送

```php
// 发送文本消息
$textMessage = $dingtalk->message()->createTextMessage('Hello, World!');
$result = $dingtalk->message()->sendWorkNotification('user_id_123', $textMessage);

// 发送链接消息
$linkMessage = $dingtalk->message()->createLinkMessage(
    '重要通知',
    '请查看最新的项目进展',
    'https://example.com/project',
    'https://example.com/image.jpg'
);
$dingtalk->message()->sendWorkNotification('user_id_123', $linkMessage);

// 发送Markdown消息
$markdownMessage = $dingtalk->message()->createMarkdownMessage(
    '项目报告',
    "# 项目进展\n\n**完成度**: 80%\n\n- [x] 需求分析\n- [x] 设计方案\n- [ ] 开发实现"
);
$dingtalk->message()->sendWorkNotification('user_id_123', $markdownMessage);

// 发送ActionCard消息
$actionCardMessage = $dingtalk->message()->createActionCardMessage(
    '审批请求',
    '您有一个待审批的请假申请',
    [
        ['title' => '同意', 'actionURL' => 'https://example.com/approve'],
        ['title' => '拒绝', 'actionURL' => 'https://example.com/reject'],
    ]
);
$dingtalk->message()->sendWorkNotification('user_id_123', $actionCardMessage);

// 群消息推送
$dingtalk->message()->sendGroupMessage('chat_id_123', $textMessage);

// 机器人消息
$dingtalk->message()->sendRobotMessage('robot_webhook_url', $textMessage, 'secret_key');
```

### 媒体文件管理

```php
// 上传图片
$imageResult = $dingtalk->media()->uploadImage('/path/to/image.jpg');
$mediaId = $imageResult['media_id'];

// 上传文件
$fileResult = $dingtalk->media()->uploadFile('/path/to/document.pdf');

// 下载媒体文件
$fileContent = $dingtalk->media()->downloadMedia($mediaId);
file_put_contents('/path/to/downloaded_file', $fileContent);

// 获取媒体文件信息
$mediaInfo = $dingtalk->media()->getMediaInfo($mediaId);

// 分片上传大文件
$uploadTask = $dingtalk->media()->createChunkedUpload('/path/to/large_file.zip', 'file');
$dingtalk->media()->uploadChunk($uploadTask['upload_id'], 1, $chunkData);
$result = $dingtalk->media()->completeChunkedUpload($uploadTask['upload_id']);
```

### 考勤管理

```php
// 获取用户考勤记录
$attendanceRecords = $dingtalk->attendance()->getUserAttendance(
    'user_id_123',
    '2024-01-01',
    '2024-01-31'
);

// 获取考勤组信息
$attendanceGroups = $dingtalk->attendance()->getAttendanceGroups();

// 获取用户考勤组
$userGroups = $dingtalk->attendance()->getUserAttendanceGroups('user_id_123');

// 创建考勤打卡记录
$checkInResult = $dingtalk->attendance()->createAttendanceRecord([
    'userId' => 'user_id_123',
    'checkType' => 'OnDuty',
    'checkTime' => time() * 1000,
    'locationResult' => 'Normal',
]);

// 获取考勤统计
$stats = $dingtalk->attendance()->getAttendanceStats(
    ['user_id_123'],
    '2024-01-01',
    '2024-01-31'
);
```

## 高级用法

### 自定义配置

```php
// 使用自定义HTTP客户端配置
$config = new ConfigManager([
    'app_key' => 'your_app_key',
    'app_secret' => 'your_app_secret',
    'http' => [
        'timeout' => 60,
        'connect_timeout' => 10,
        'verify' => true,
        'headers' => [
            'User-Agent' => 'Custom-DingTalk-SDK/1.0',
        ],
    ],
]);
```

### 缓存配置

```php
// 内存缓存（默认）
$config->set('cache', [
    'driver' => 'memory',
    'prefix' => 'dingtalk_',
    'default_ttl' => 3600,
]);

// 文件缓存
$config->set('cache', [
    'driver' => 'file',
    'path' => '/tmp/dingtalk_cache',
    'prefix' => 'dingtalk_',
    'default_ttl' => 3600,
]);

// Redis缓存
$config->set('cache', [
    'driver' => 'redis',
    'host' => '127.0.0.1',
    'port' => 6379,
    'database' => 0,
    'prefix' => 'dingtalk_',
    'default_ttl' => 3600,
]);
```

### 日志配置

```php
$config->set('log', [
    'enabled' => true,
    'level' => 'debug',
    'handlers' => [
        [
            'type' => 'file',
            'path' => '/var/log/dingtalk.log',
            'level' => 'info',
            'max_files' => 30,
        ],
        [
            'type' => 'console',
            'level' => 'debug',
            'colored' => true,
        ],
    ],
]);
```

### 链式调用

```php
// 链式调用示例
$result = $dingtalk
    ->user()
    ->getUserInfo('user_id_123');

$message = $dingtalk
    ->message()
    ->createTextMessage('Hello')
    ->sendWorkNotification('user_id_123', $message);
```

### 批量操作

```php
// 批量获取用户信息
$userIds = ['user1', 'user2', 'user3'];
$users = $dingtalk->user()->getUserInfoBatch($userIds);

// 批量发送消息
$userIds = ['user1', 'user2', 'user3'];
$message = $dingtalk->message()->createTextMessage('批量消息');
$results = $dingtalk->message()->sendBatchMessages($userIds, $message);
```

### 错误处理

```php
use DingTalk\Exceptions\DingTalkException;
use DingTalk\Exceptions\AuthException;
use DingTalk\Exceptions\ApiException;

try {
    $userInfo = $dingtalk->user()->getUserInfo('invalid_user_id');
} catch (AuthException $e) {
    // 认证错误
    echo "认证失败: " . $e->getMessage();
} catch (ApiException $e) {
    // API调用错误
    echo "API错误: " . $e->getMessage();
    echo "错误代码: " . $e->getCode();
} catch (DingTalkException $e) {
    // 其他钉钉相关错误
    echo "钉钉SDK错误: " . $e->getMessage();
} catch (\Exception $e) {
    // 其他错误
    echo "未知错误: " . $e->getMessage();
}
```

### 使用服务容器

```php
// 获取服务容器
$container = $dingtalk->getContainer();

// 直接获取服务
$config = $container->get('config');
$cache = $container->get('cache');
$logger = $container->get('logger');
$http = $container->get('http');

// 注册自定义服务
$container->set('custom_service', function() {
    return new CustomService();
});
```

## API 参考

### 用户服务 (UserService)

| 方法 | 描述 | 参数 |
|------|------|------|
| `getUserInfo($userId)` | 获取用户详情 | `$userId`: 用户ID |
| `getUserIdByMobile($mobile)` | 通过手机号获取用户ID | `$mobile`: 手机号 |
| `getUserIdByUnionId($unionId)` | 通过unionId获取用户ID | `$unionId`: unionId |
| `getDepartmentUsers($deptId, $offset, $size)` | 获取部门用户列表 | `$deptId`: 部门ID, `$offset`: 偏移量, `$size`: 数量 |
| `createUser($userData)` | 创建用户 | `$userData`: 用户数据数组 |
| `updateUser($userId, $userData)` | 更新用户 | `$userId`: 用户ID, `$userData`: 更新数据 |
| `deleteUser($userId)` | 删除用户 | `$userId`: 用户ID |

### 部门服务 (DepartmentService)

| 方法 | 描述 | 参数 |
|------|------|------|
| `getDepartmentInfo($deptId)` | 获取部门详情 | `$deptId`: 部门ID |
| `getSubDepartments($deptId)` | 获取子部门列表 | `$deptId`: 父部门ID |
| `createDepartment($deptData)` | 创建部门 | `$deptData`: 部门数据数组 |
| `updateDepartment($deptId, $deptData)` | 更新部门 | `$deptId`: 部门ID, `$deptData`: 更新数据 |
| `deleteDepartment($deptId)` | 删除部门 | `$deptId`: 部门ID |

### 消息服务 (MessageService)

| 方法 | 描述 | 参数 |
|------|------|------|
| `createTextMessage($content)` | 创建文本消息 | `$content`: 消息内容 |
| `createLinkMessage($title, $text, $messageUrl, $picUrl)` | 创建链接消息 | 标题、描述、链接、图片URL |
| `createMarkdownMessage($title, $text)` | 创建Markdown消息 | `$title`: 标题, `$text`: Markdown内容 |
| `sendWorkNotification($userId, $message)` | 发送工作通知 | `$userId`: 用户ID, `$message`: 消息内容 |
| `sendGroupMessage($chatId, $message)` | 发送群消息 | `$chatId`: 群ID, `$message`: 消息内容 |

### 媒体服务 (MediaService)

| 方法 | 描述 | 参数 |
|------|------|------|
| `uploadImage($filePath)` | 上传图片 | `$filePath`: 文件路径 |
| `uploadFile($filePath)` | 上传文件 | `$filePath`: 文件路径 |
| `downloadMedia($mediaId)` | 下载媒体文件 | `$mediaId`: 媒体ID |
| `getMediaInfo($mediaId)` | 获取媒体信息 | `$mediaId`: 媒体ID |

### 考勤服务 (AttendanceService)

| 方法 | 描述 | 参数 |
|------|------|------|
| `getUserAttendance($userId, $startDate, $endDate)` | 获取用户考勤记录 | 用户ID、开始日期、结束日期 |
| `getAttendanceGroups()` | 获取考勤组列表 | 无 |
| `createAttendanceRecord($recordData)` | 创建考勤记录 | `$recordData`: 考勤数据 |

## 测试

运行测试套件：

```bash
# 运行所有测试
composer test

# 运行单元测试
./vendor/bin/phpunit tests/Unit

# 运行集成测试
./vendor/bin/phpunit tests/Integration

# 运行功能测试
./vendor/bin/phpunit tests/Feature

# 生成测试覆盖率报告
composer test-coverage
```

## 贡献

欢迎贡献代码！请遵循以下步骤：

1. Fork 本仓库
2. 创建特性分支 (`git checkout -b feature/amazing-feature`)
3. 提交更改 (`git commit -m 'Add some amazing feature'`)
4. 推送到分支 (`git push origin feature/amazing-feature`)
5. 创建 Pull Request

### 开发指南

1. 确保代码符合 PSR-12 编码标准
2. 添加适当的类型提示和文档注释
3. 为新功能编写测试
4. 更新相关文档

## 许可证

本项目采用 MIT 许可证。详情请参阅 [LICENSE](LICENSE) 文件。

## 更新日志

### v1.0.0 (2024-01-01)

- 初始版本发布
- 支持用户管理、部门管理、消息推送、媒体文件管理、考勤管理
- 完整的缓存和日志系统
- 全面的测试覆盖

## 支持

如果您在使用过程中遇到问题，请：

1. 查看 [文档](docs/)
2. 搜索 [Issues](https://github.com/your-vendor/dingtalk-sdk/issues)
3. 创建新的 [Issue](https://github.com/your-vendor/dingtalk-sdk/issues/new)

## 相关链接

- [钉钉开放平台](https://open.dingtalk.com/)
- [钉钉开发者文档](https://developers.dingtalk.com/)
- [API 参考文档](https://developers.dingtalk.com/document/)