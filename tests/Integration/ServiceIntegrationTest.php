<?php

declare(strict_types=1);

namespace DingTalk\Tests\Integration;

use PHPUnit\Framework\TestCase;
use DingTalk\DingTalk;
use DingTalk\Config\ConfigManager;
use DingTalk\Services\UserService;
use DingTalk\Services\DepartmentService;
use DingTalk\Services\MessageService;

/**
 * 服务集成测试
 */
class ServiceIntegrationTest extends TestCase
{
    private DingTalk $dingtalk;
    private ConfigManager $config;

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
                'prefix' => 'test_',
                'default_ttl' => 3600,
            ],
            'log' => [
                'enabled' => false,
                'level' => 'info',
            ],
        ];

        $this->config = new ConfigManager($config);
        $this->dingtalk = new DingTalk($config);
    }

    public function testServiceInitialization(): void
    {
        // 测试所有服务都能正确初始化
        $this->assertInstanceOf(UserService::class, $this->dingtalk->user());
        $this->assertInstanceOf(DepartmentService::class, $this->dingtalk->department());
        $this->assertInstanceOf(MessageService::class, $this->dingtalk->message());
    }

    public function testServiceDependencyInjection(): void
    {
        $userService = $this->dingtalk->user();
        
        // 使用反射检查依赖注入
        $reflection = new \ReflectionClass($userService);
        
        // 检查配置管理器
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $this->assertSame($this->config, $configProperty->getValue($userService));

        // 检查缓存管理器
        $cacheProperty = $reflection->getProperty('cache');
        $cacheProperty->setAccessible(true);
        $this->assertSame($this->dingtalk->cache(), $cacheProperty->getValue($userService));

        // 检查日志管理器
        $loggerProperty = $reflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $this->assertSame($this->dingtalk->logger(), $loggerProperty->getValue($userService));
    }

    public function testCacheIntegrationAcrossServices(): void
    {
        $cache = $this->dingtalk->cache();
        $userService = $this->dingtalk->user();
        $departmentService = $this->dingtalk->department();

        // 通过用户服务设置缓存
        $cache->set('shared_key', 'shared_value', 3600);

        // 通过部门服务获取缓存
        $this->assertEquals('shared_value', $cache->get('shared_key'));

        // 测试缓存键构建的一致性
        $reflection = new \ReflectionClass($userService);
        $method = $reflection->getMethod('buildCacheKey');
        $method->setAccessible(true);

        $userCacheKey = $method->invoke($userService, 'user', ['id' => '123']);
        
        $reflection = new \ReflectionClass($departmentService);
        $method = $reflection->getMethod('buildCacheKey');
        $method->setAccessible(true);

        $deptCacheKey = $method->invoke($departmentService, 'user', ['id' => '123']);

        // 相同的参数应该生成相同的缓存键
        $this->assertEquals($userCacheKey, $deptCacheKey);
    }

    public function testMessageCreationIntegration(): void
    {
        $messageService = $this->dingtalk->message();

        // 测试各种消息类型的创建
        $textMessage = $messageService->createTextMessage('Hello World');
        $this->assertArrayHasKey('msgtype', $textMessage);
        $this->assertEquals('text', $textMessage['msgtype']);

        $linkMessage = $messageService->createLinkMessage(
            'Test Link',
            'Test Description',
            'https://example.com',
            'https://example.com/pic.jpg'
        );
        $this->assertEquals('link', $linkMessage['msgtype']);

        $markdownMessage = $messageService->createMarkdownMessage(
            'Markdown Title',
            '## Test Markdown'
        );
        $this->assertEquals('markdown', $markdownMessage['msgtype']);

        // 测试ActionCard消息
        $actionCardMessage = $messageService->createActionCardMessage(
            'Action Title',
            'Action Description',
            [
                ['title' => 'Button 1', 'actionURL' => 'https://example.com/1'],
                ['title' => 'Button 2', 'actionURL' => 'https://example.com/2'],
            ]
        );
        $this->assertEquals('actionCard', $actionCardMessage['msgtype']);
        $this->assertCount(2, $actionCardMessage['actionCard']['btns']);

        // 测试单按钮ActionCard
        $singleActionCard = $messageService->createActionCardMessage(
            'Single Action',
            'Single Description',
            [['title' => 'Single Button', 'actionURL' => 'https://example.com']]
        );
        $this->assertArrayHasKey('singleTitle', $singleActionCard['actionCard']);
        $this->assertArrayHasKey('singleURL', $singleActionCard['actionCard']);
    }

    public function testParameterValidationAcrossServices(): void
    {
        $userService = $this->dingtalk->user();
        $departmentService = $this->dingtalk->department();

        // 测试用户服务参数验证
        $reflection = new \ReflectionClass($userService);
        $method = $reflection->getMethod('validateRequired');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($userService, [], ['userId']);
    }

    public function testArrayFilteringConsistency(): void
    {
        $userService = $this->dingtalk->user();
        $departmentService = $this->dingtalk->department();

        $testData = [
            'key1' => 'value1',
            'key2' => '',
            'key3' => null,
            'key4' => 0,
            'key5' => false,
            'key6' => 'value6',
        ];

        $expected = [
            'key1' => 'value1',
            'key4' => 0,
            'key5' => false,
            'key6' => 'value6',
        ];

        // 测试用户服务的过滤
        $reflection = new \ReflectionClass($userService);
        $method = $reflection->getMethod('filterEmptyValues');
        $method->setAccessible(true);
        $userFiltered = $method->invoke($userService, $testData);

        // 测试部门服务的过滤
        $reflection = new \ReflectionClass($departmentService);
        $method = $reflection->getMethod('filterEmptyValues');
        $method->setAccessible(true);
        $deptFiltered = $method->invoke($departmentService, $testData);

        // 两个服务应该产生相同的过滤结果
        $this->assertEquals($expected, $userFiltered);
        $this->assertEquals($expected, $deptFiltered);
        $this->assertEquals($userFiltered, $deptFiltered);
    }

    public function testTimestampConversionConsistency(): void
    {
        $userService = $this->dingtalk->user();
        $departmentService = $this->dingtalk->department();

        $testDate = '2024-01-01 12:00:00';
        $expectedTimestamp = strtotime($testDate) * 1000;

        // 测试用户服务的时间戳转换
        $reflection = new \ReflectionClass($userService);
        $method = $reflection->getMethod('convertTimestamp');
        $method->setAccessible(true);
        $userTimestamp = $method->invoke($userService, $testDate);

        // 测试部门服务的时间戳转换
        $reflection = new \ReflectionClass($departmentService);
        $method = $reflection->getMethod('convertTimestamp');
        $method->setAccessible(true);
        $deptTimestamp = $method->invoke($departmentService, $testDate);

        // 两个服务应该产生相同的时间戳
        $this->assertEquals($expectedTimestamp, $userTimestamp);
        $this->assertEquals($expectedTimestamp, $deptTimestamp);
        $this->assertEquals($userTimestamp, $deptTimestamp);
    }

    public function testContainerIntegration(): void
    {
        $container = $this->dingtalk->getContainer();

        // 测试容器中的服务
        $this->assertTrue($container->has('config'));
        $this->assertTrue($container->has('http'));
        $this->assertTrue($container->has('auth'));
        $this->assertTrue($container->has('cache'));
        $this->assertTrue($container->has('logger'));

        // 测试服务实例
        $this->assertSame($this->config, $container->get('config'));
        $this->assertSame($this->dingtalk->cache(), $container->get('cache'));
        $this->assertSame($this->dingtalk->logger(), $container->get('logger'));
    }

    public function testConfigurationPropagation(): void
    {
        // 测试配置变更是否正确传播到所有服务
        $this->config->set('test_key', 'test_value');

        $userService = $this->dingtalk->user();
        $departmentService = $this->dingtalk->department();

        // 使用反射获取服务中的配置
        $reflection = new \ReflectionClass($userService);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $userConfig = $configProperty->getValue($userService);

        $reflection = new \ReflectionClass($departmentService);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $deptConfig = $configProperty->getValue($departmentService);

        // 所有服务应该使用相同的配置实例
        $this->assertSame($this->config, $userConfig);
        $this->assertSame($this->config, $deptConfig);
        $this->assertEquals('test_value', $userConfig->get('test_key'));
        $this->assertEquals('test_value', $deptConfig->get('test_key'));
    }

    protected function tearDown(): void
    {
        $this->dingtalk->cache()->clear();
    }
}