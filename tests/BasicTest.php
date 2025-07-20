<?php

declare(strict_types=1);

namespace DingTalk\Tests;

use PHPUnit\Framework\TestCase;
use DingTalk\DingTalk;
use DingTalk\Config\ConfigManager;
use DingTalk\Exceptions\ConfigException;

/**
 * 基础功能测试
 */
class BasicTest extends TestCase
{
    private ConfigManager $config;
    private DingTalk $dingtalk;

    protected function setUp(): void
    {
        $config = [
            'app_key' => 'ding7hrupllg8fdrn21u',
            'app_secret' => '3gijKW7WEa-2tZ83ygL-8zDGSWmLW4JNXNdqZZfDlYwLlmxRLwHyEtKdepoYyQca',
            'agent_id' => '3594517592',
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

    public function testConfigManager(): void
    {
        // 测试配置获取
        $this->assertEquals('test_app_key', $this->config->get('app_key'));
        $this->assertEquals('v1', $this->config->get('api_version'));
        $this->assertEquals(30, $this->config->get('timeout'));

        // 测试配置设置
        $this->config->set('test_key', 'test_value');
        $this->assertEquals('test_value', $this->config->get('test_key'));

        // 测试默认值
        $this->assertEquals('default', $this->config->get('nonexistent_key', 'default'));

        // 测试嵌套配置
        $this->assertEquals('memory', $this->config->get('cache.driver'));
        $this->assertEquals('test_', $this->config->get('cache.prefix'));
    }

    public function testConfigValidation(): void
    {
        // 测试必需配置缺失
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Required configuration key is missing: app_key');

        new ConfigManager([
            'app_secret' => 'test_secret',
        ]);
    }

    public function testDingTalkInitialization(): void
    {
        // 测试钉钉客户端初始化
        $this->assertInstanceOf(DingTalk::class, $this->dingtalk);

        // 测试服务获取
        $this->assertNotNull($this->dingtalk->user());
        $this->assertNotNull($this->dingtalk->department());
        $this->assertNotNull($this->dingtalk->message());
        $this->assertNotNull($this->dingtalk->media());
        $this->assertNotNull($this->dingtalk->attendance());

        // 测试容器
        $container = $this->dingtalk->getContainer();
        $this->assertNotNull($container);
        $this->assertTrue($container->has('config'));
        $this->assertTrue($container->has('http'));
        $this->assertTrue($container->has('auth'));
        $this->assertTrue($container->has('cache'));
        $this->assertTrue($container->has('logger'));
    }

    public function testCacheManager(): void
    {
        $cache = $this->dingtalk->cache();

        // 测试基本缓存操作
        $cache->set('test_key', 'test_value', 3600);
        $this->assertEquals('test_value', $cache->get('test_key'));
        $this->assertTrue($cache->has('test_key'));

        // 测试缓存删除
        $cache->delete('test_key');
        $this->assertFalse($cache->has('test_key'));
        $this->assertNull($cache->get('test_key'));

        // 测试批量操作
        $cache->setMultiple([
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ], 3600);

        $values = $cache->getMultiple(['key1', 'key2', 'key3']);
        $this->assertEquals([
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ], $values);

        // 测试remember方法
        $result = $cache->remember('computed_key', function () {
            return 'computed_value';
        }, 3600);
        $this->assertEquals('computed_value', $result);

        // 再次调用应该从缓存获取
        $result2 = $cache->remember('computed_key', function () {
            return 'new_computed_value';
        }, 3600);
        $this->assertEquals('computed_value', $result2);
    }

    public function testMessageCreation(): void
    {
        $message = $this->dingtalk->message();

        // 测试文本消息创建
        $textMessage = $message->createTextMessage('Hello, World!');
        $this->assertEquals('text', $textMessage['msgtype']);
        $this->assertEquals('Hello, World!', $textMessage['text']['content']);

        // 测试链接消息创建
        $linkMessage = $message->createLinkMessage(
            'Test Title',
            'Test Description',
            'https://example.com',
            'https://example.com/image.jpg'
        );
        $this->assertEquals('link', $linkMessage['msgtype']);
        $this->assertEquals('Test Title', $linkMessage['link']['title']);
        $this->assertEquals('Test Description', $linkMessage['link']['text']);
        $this->assertEquals('https://example.com', $linkMessage['link']['messageUrl']);
        $this->assertEquals('https://example.com/image.jpg', $linkMessage['link']['picUrl']);

        // 测试Markdown消息创建
        $markdownMessage = $message->createMarkdownMessage(
            'Markdown Title',
            '## Heading\n\n**Bold text**'
        );
        $this->assertEquals('markdown', $markdownMessage['msgtype']);
        $this->assertEquals('Markdown Title', $markdownMessage['markdown']['title']);
        $this->assertEquals('## Heading\n\n**Bold text**', $markdownMessage['markdown']['text']);

        // 测试ActionCard消息创建
        $actionCardMessage = $message->createActionCardMessage(
            'Action Title',
            'Action Description',
            [
                ['title' => 'Button 1', 'actionURL' => 'https://example.com/1'],
                ['title' => 'Button 2', 'actionURL' => 'https://example.com/2'],
            ]
        );
        $this->assertEquals('actionCard', $actionCardMessage['msgtype']);
        $this->assertEquals('Action Title', $actionCardMessage['actionCard']['title']);
        $this->assertCount(2, $actionCardMessage['actionCard']['btns']);

        // 测试单按钮ActionCard
        $singleActionCard = $message->createActionCardMessage(
            'Single Action',
            'Single Description',
            [['title' => 'Single Button', 'actionURL' => 'https://example.com']]
        );
        $this->assertEquals('Single Button', $singleActionCard['actionCard']['singleTitle']);
        $this->assertEquals('https://example.com', $singleActionCard['actionCard']['singleURL']);
    }

    public function testParameterValidation(): void
    {
        // 测试参数验证
        $user = $this->dingtalk->user();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Required parameter is missing: userId');

        // 这应该触发参数验证错误
        $reflection = new \ReflectionClass($user);
        $method = $reflection->getMethod('validateRequired');
        $method->setAccessible(true);
        $method->invoke($user, [], ['userId']);
    }

    public function testCacheKeyBuilding(): void
    {
        $user = $this->dingtalk->user();

        // 使用反射测试缓存键构建
        $reflection = new \ReflectionClass($user);
        $method = $reflection->getMethod('buildCacheKey');
        $method->setAccessible(true);

        $cacheKey = $method->invoke($user, 'user', ['userId' => 'test123']);
        $this->assertStringContains('user', $cacheKey);
        $this->assertStringContains('test123', $cacheKey);
    }

    public function testArrayFiltering(): void
    {
        $user = $this->dingtalk->user();

        // 使用反射测试数组过滤
        $reflection = new \ReflectionClass($user);
        $method = $reflection->getMethod('filterEmptyValues');
        $method->setAccessible(true);

        $input = [
            'key1' => 'value1',
            'key2' => '',
            'key3' => null,
            'key4' => 0,
            'key5' => false,
            'key6' => 'value6',
        ];

        $filtered = $method->invoke($user, $input);
        $expected = [
            'key1' => 'value1',
            'key4' => 0,
            'key5' => false,
            'key6' => 'value6',
        ];

        $this->assertEquals($expected, $filtered);
    }

    public function testTimestampConversion(): void
    {
        $user = $this->dingtalk->user();

        // 使用反射测试时间戳转换
        $reflection = new \ReflectionClass($user);
        $method = $reflection->getMethod('convertTimestamp');
        $method->setAccessible(true);

        // 测试日期字符串转换
        $timestamp = $method->invoke($user, '2024-01-01 12:00:00');
        $this->assertEquals(strtotime('2024-01-01 12:00:00') * 1000, $timestamp);

        // 测试已经是时间戳的情况
        $existingTimestamp = 1704110400000;
        $result = $method->invoke($user, $existingTimestamp);
        $this->assertEquals($existingTimestamp, $result);
    }

    protected function tearDown(): void
    {
        // 清理缓存
        $this->dingtalk->cache()->clear();
    }
}
