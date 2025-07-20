<?php

declare(strict_types=1);

namespace DingTalk\Tests\Feature;

use PHPUnit\Framework\TestCase;
use DingTalk\DingTalk;
use DingTalk\Config\ConfigManager;

/**
 * 完整功能测试
 */
class FullWorkflowTest extends TestCase
{
    private DingTalk $dingtalk;

    protected function setUp(): void
    {
        $config = [
            'app_key' => 'test_app_key',
            'app_secret' => 'test_app_secret',
            'agent_id' => 'test_agent_id',
            'api_version' => 'v1',
            'base_url' => 'https://oapi.dingtalk.com',
            'timeout' => 30,
            'cache' => [
                'driver' => 'memory',
                'prefix' => 'dingtalk_',
                'default_ttl' => 3600,
            ],
            'log' => [
                'enabled' => true,
                'level' => 'debug',
                'handlers' => [
                    [
                        'type' => 'console',
                        'level' => 'debug',
                        'colored' => true,
                    ],
                ],
            ],
        ];

        $this->dingtalk = new DingTalk($config);
    }

    public function testCompleteUserWorkflow(): void
    {
        $userService = $this->dingtalk->user();
        $cache = $this->dingtalk->cache();

        // 模拟用户数据
        $userId = 'test_user_123';
        $mobile = '13800138000';
        $unionId = 'union_123';

        // 测试缓存键构建
        $reflection = new \ReflectionClass($userService);
        $buildCacheKeyMethod = $reflection->getMethod('buildCacheKey');
        $buildCacheKeyMethod->setAccessible(true);

        $userCacheKey = $buildCacheKeyMethod->invoke($userService, 'user', ['userId' => $userId]);
        $mobileCacheKey = $buildCacheKeyMethod->invoke($userService, 'user_by_mobile', ['mobile' => $mobile]);

        // 模拟缓存用户数据
        $userData = [
            'userid' => $userId,
            'name' => 'Test User',
            'mobile' => $mobile,
            'unionid' => $unionId,
            'department' => [1, 2],
            'position' => 'Developer',
            'email' => 'test@example.com',
        ];

        $cache->set($userCacheKey, $userData, 3600);
        $cache->set($mobileCacheKey, $userId, 3600);

        // 测试从缓存获取用户数据
        $cachedUserData = $cache->get($userCacheKey);
        $this->assertEquals($userData, $cachedUserData);

        $cachedUserId = $cache->get($mobileCacheKey);
        $this->assertEquals($userId, $cachedUserId);

        // 测试参数验证
        $validateRequiredMethod = $reflection->getMethod('validateRequired');
        $validateRequiredMethod->setAccessible(true);

        // 有效参数应该不抛出异常
        $validateRequiredMethod->invoke($userService, ['userId' => $userId], ['userId']);

        // 无效参数应该抛出异常
        $this->expectException(\InvalidArgumentException::class);
        $validateRequiredMethod->invoke($userService, [], ['userId']);
    }

    public function testCompleteDepartmentWorkflow(): void
    {
        $departmentService = $this->dingtalk->department();
        $cache = $this->dingtalk->cache();

        // 模拟部门数据
        $deptId = 1;
        $deptData = [
            'id' => $deptId,
            'name' => 'Test Department',
            'parentid' => 0,
            'order' => 1,
            'createDeptGroup' => true,
            'autoAddUser' => true,
        ];

        // 测试缓存键构建
        $reflection = new \ReflectionClass($departmentService);
        $buildCacheKeyMethod = $reflection->getMethod('buildCacheKey');
        $buildCacheKeyMethod->setAccessible(true);

        $deptCacheKey = $buildCacheKeyMethod->invoke($departmentService, 'department', ['dept_id' => $deptId]);

        // 缓存部门数据
        $cache->set($deptCacheKey, $deptData, 3600);

        // 验证缓存数据
        $cachedDeptData = $cache->get($deptCacheKey);
        $this->assertEquals($deptData, $cachedDeptData);

        // 测试子部门列表缓存
        $subDeptCacheKey = $buildCacheKeyMethod->invoke($departmentService, 'sub_departments', ['dept_id' => $deptId]);
        $subDeptData = [
            ['id' => 2, 'name' => 'Sub Dept 1', 'parentid' => $deptId],
            ['id' => 3, 'name' => 'Sub Dept 2', 'parentid' => $deptId],
        ];

        $cache->set($subDeptCacheKey, $subDeptData, 3600);
        $cachedSubDeptData = $cache->get($subDeptCacheKey);
        $this->assertEquals($subDeptData, $cachedSubDeptData);
    }

    public function testCompleteMessageWorkflow(): void
    {
        $messageService = $this->dingtalk->message();

        // 测试各种消息类型的完整工作流
        $messages = [];

        // 1. 文本消息
        $textMessage = $messageService->createTextMessage('Hello, this is a test message!');
        $messages['text'] = $textMessage;
        $this->assertEquals('text', $textMessage['msgtype']);
        $this->assertEquals('Hello, this is a test message!', $textMessage['text']['content']);

        // 2. 链接消息
        $linkMessage = $messageService->createLinkMessage(
            'Test Link Title',
            'This is a test link description',
            'https://example.com/test',
            'https://example.com/test.jpg'
        );
        $messages['link'] = $linkMessage;
        $this->assertEquals('link', $linkMessage['msgtype']);

        // 3. Markdown消息
        $markdownMessage = $messageService->createMarkdownMessage(
            'Markdown Test',
            "# Test Markdown\n\n**Bold text** and *italic text*\n\n- List item 1\n- List item 2"
        );
        $messages['markdown'] = $markdownMessage;
        $this->assertEquals('markdown', $markdownMessage['msgtype']);

        // 4. ActionCard消息（多按钮）
        $multiActionCard = $messageService->createActionCardMessage(
            'Multi Action Card',
            'Please choose an action:',
            [
                ['title' => 'Approve', 'actionURL' => 'https://example.com/approve'],
                ['title' => 'Reject', 'actionURL' => 'https://example.com/reject'],
                ['title' => 'View Details', 'actionURL' => 'https://example.com/details'],
            ]
        );
        $messages['multi_action'] = $multiActionCard;
        $this->assertEquals('actionCard', $multiActionCard['msgtype']);
        $this->assertCount(3, $multiActionCard['actionCard']['btns']);

        // 5. ActionCard消息（单按钮）
        $singleActionCard = $messageService->createActionCardMessage(
            'Single Action Card',
            'Click the button below to continue:',
            [['title' => 'Continue', 'actionURL' => 'https://example.com/continue']]
        );
        $messages['single_action'] = $singleActionCard;
        $this->assertEquals('actionCard', $singleActionCard['msgtype']);
        $this->assertArrayHasKey('singleTitle', $singleActionCard['actionCard']);

        // 6. FeedCard消息
        $feedCardMessage = $messageService->createFeedCardMessage([
            [
                'title' => 'Feed Item 1',
                'messageURL' => 'https://example.com/feed1',
                'picURL' => 'https://example.com/pic1.jpg',
            ],
            [
                'title' => 'Feed Item 2',
                'messageURL' => 'https://example.com/feed2',
                'picURL' => 'https://example.com/pic2.jpg',
            ],
        ]);
        $messages['feed_card'] = $feedCardMessage;
        $this->assertEquals('feedCard', $feedCardMessage['msgtype']);
        $this->assertCount(2, $feedCardMessage['feedCard']['links']);

        // 验证所有消息都有正确的结构
        foreach ($messages as $type => $message) {
            $this->assertArrayHasKey('msgtype', $message);
            $this->assertNotEmpty($message['msgtype']);
        }
    }

    public function testCompleteMediaWorkflow(): void
    {
        $mediaService = $this->dingtalk->media();
        $cache = $this->dingtalk->cache();

        // 模拟媒体文件数据
        $mediaId = 'test_media_123';
        $mediaInfo = [
            'media_id' => $mediaId,
            'type' => 'image',
            'size' => 1024000,
            'created_at' => time(),
            'url' => 'https://example.com/media/' . $mediaId,
        ];

        // 测试缓存键构建
        $reflection = new \ReflectionClass($mediaService);
        $buildCacheKeyMethod = $reflection->getMethod('buildCacheKey');
        $buildCacheKeyMethod->setAccessible(true);

        $mediaCacheKey = $buildCacheKeyMethod->invoke($mediaService, 'media_info', ['media_id' => $mediaId]);

        // 缓存媒体信息
        $cache->set($mediaCacheKey, $mediaInfo, 3600);

        // 验证缓存数据
        $cachedMediaInfo = $cache->get($mediaCacheKey);
        $this->assertEquals($mediaInfo, $cachedMediaInfo);

        // 测试上传进度缓存
        $progressCacheKey = $buildCacheKeyMethod->invoke($mediaService, 'upload_progress', ['upload_id' => 'upload_123']);
        $progressData = [
            'upload_id' => 'upload_123',
            'progress' => 75,
            'status' => 'uploading',
            'uploaded_size' => 750000,
            'total_size' => 1000000,
        ];

        $cache->set($progressCacheKey, $progressData, 1800); // 30分钟过期
        $cachedProgressData = $cache->get($progressCacheKey);
        $this->assertEquals($progressData, $cachedProgressData);
    }

    public function testCompleteAttendanceWorkflow(): void
    {
        $attendanceService = $this->dingtalk->attendance();
        $cache = $this->dingtalk->cache();

        // 模拟考勤数据
        $userId = 'test_user_123';
        $date = '2024-01-01';
        $attendanceData = [
            'userid' => $userId,
            'date' => $date,
            'records' => [
                [
                    'checkType' => 'OnDuty',
                    'userCheckTime' => strtotime($date . ' 09:00:00') * 1000,
                    'locationResult' => 'Normal',
                ],
                [
                    'checkType' => 'OffDuty',
                    'userCheckTime' => strtotime($date . ' 18:00:00') * 1000,
                    'locationResult' => 'Normal',
                ],
            ],
        ];

        // 测试缓存键构建
        $reflection = new \ReflectionClass($attendanceService);
        $buildCacheKeyMethod = $reflection->getMethod('buildCacheKey');
        $buildCacheKeyMethod->setAccessible(true);

        $attendanceCacheKey = $buildCacheKeyMethod->invoke(
            $attendanceService,
            'attendance_records',
            ['userId' => $userId, 'date' => $date]
        );

        // 缓存考勤数据
        $cache->set($attendanceCacheKey, $attendanceData, 3600);

        // 验证缓存数据
        $cachedAttendanceData = $cache->get($attendanceCacheKey);
        $this->assertEquals($attendanceData, $cachedAttendanceData);

        // 测试考勤组信息缓存
        $groupId = 'group_123';
        $groupCacheKey = $buildCacheKeyMethod->invoke(
            $attendanceService,
            'attendance_group',
            ['group_id' => $groupId]
        );

        $groupData = [
            'id' => $groupId,
            'name' => 'Test Attendance Group',
            'memberCount' => 50,
            'workDayList' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
        ];

        $cache->set($groupCacheKey, $groupData, 7200); // 2小时过期
        $cachedGroupData = $cache->get($groupCacheKey);
        $this->assertEquals($groupData, $cachedGroupData);
    }

    public function testCacheConsistencyAcrossServices(): void
    {
        $cache = $this->dingtalk->cache();

        // 设置一些跨服务的共享数据
        $sharedData = [
            'user_123' => ['name' => 'Test User', 'dept_id' => 1],
            'dept_1' => ['name' => 'Test Department', 'parent_id' => 0],
            'config_key' => 'config_value',
        ];

        foreach ($sharedData as $key => $value) {
            $cache->set($key, $value, 3600);
        }

        // 通过不同服务访问相同的缓存数据
        $userService = $this->dingtalk->user();
        $departmentService = $this->dingtalk->department();
        $messageService = $this->dingtalk->message();

        // 所有服务都应该能访问到相同的缓存数据
        foreach ($sharedData as $key => $expectedValue) {
            $this->assertEquals($expectedValue, $cache->get($key));
        }

        // 测试缓存统计
        $driver = $cache->getDriver();
        $stats = $driver->getStats();
        $this->assertGreaterThanOrEqual(count($sharedData), $stats['keys']);
    }

    public function testErrorHandlingWorkflow(): void
    {
        // 测试各种错误处理场景
        $userService = $this->dingtalk->user();

        // 测试参数验证错误
        $reflection = new \ReflectionClass($userService);
        $validateMethod = $reflection->getMethod('validateRequired');
        $validateMethod->setAccessible(true);

        try {
            $validateMethod->invoke($userService, [], ['required_param']);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContains('Required parameter is missing', $e->getMessage());
        }

        // 测试数组过滤
        $filterMethod = $reflection->getMethod('filterEmptyValues');
        $filterMethod->setAccessible(true);

        $testArray = [
            'valid' => 'value',
            'empty_string' => '',
            'null_value' => null,
            'zero' => 0,
            'false' => false,
            'empty_array' => [],
        ];

        $filtered = $filterMethod->invoke($userService, $testArray);
        $this->assertArrayHasKey('valid', $filtered);
        $this->assertArrayHasKey('zero', $filtered);
        $this->assertArrayHasKey('false', $filtered);
        $this->assertArrayNotHasKey('empty_string', $filtered);
        $this->assertArrayNotHasKey('null_value', $filtered);
        $this->assertArrayNotHasKey('empty_array', $filtered);
    }

    protected function tearDown(): void
    {
        $this->dingtalk->cache()->clear();
    }
}