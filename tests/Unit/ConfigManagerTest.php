<?php

declare(strict_types=1);

namespace DingTalk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use DingTalk\Config\ConfigManager;
use DingTalk\Exceptions\ConfigException;

/**
 * 配置管理器单元测试
 */
class ConfigManagerTest extends TestCase
{
    public function testBasicConfiguration(): void
    {
        $config = new ConfigManager([
            'app_key' => 'test_key',
            'app_secret' => 'test_secret',
            'agent_id' => 'test_agent',
        ]);

        $this->assertEquals('test_key', $config->get('app_key'));
        $this->assertEquals('test_secret', $config->get('app_secret'));
        $this->assertEquals('test_agent', $config->get('agent_id'));
    }

    public function testNestedConfiguration(): void
    {
        $config = new ConfigManager([
            'app_key' => 'test_key',
            'app_secret' => 'test_secret',
            'cache' => [
                'driver' => 'memory',
                'prefix' => 'test_',
                'default_ttl' => 3600,
            ],
        ]);

        $this->assertEquals('memory', $config->get('cache.driver'));
        $this->assertEquals('test_', $config->get('cache.prefix'));
        $this->assertEquals(3600, $config->get('cache.default_ttl'));
    }

    public function testDefaultValues(): void
    {
        $config = new ConfigManager([
            'app_key' => 'test_key',
            'app_secret' => 'test_secret',
        ]);

        $this->assertEquals('default_value', $config->get('nonexistent', 'default_value'));
        $this->assertNull($config->get('nonexistent'));
    }

    public function testSetConfiguration(): void
    {
        $config = new ConfigManager([
            'app_key' => 'test_key',
            'app_secret' => 'test_secret',
        ]);

        $config->set('new_key', 'new_value');
        $this->assertEquals('new_value', $config->get('new_key'));

        $config->set('nested.key', 'nested_value');
        $this->assertEquals('nested_value', $config->get('nested.key'));
    }

    public function testHasConfiguration(): void
    {
        $config = new ConfigManager([
            'app_key' => 'test_key',
            'app_secret' => 'test_secret',
            'cache' => [
                'driver' => 'memory',
            ],
        ]);

        $this->assertTrue($config->has('app_key'));
        $this->assertTrue($config->has('cache.driver'));
        $this->assertFalse($config->has('nonexistent'));
        $this->assertFalse($config->has('cache.nonexistent'));
    }

    public function testAllConfiguration(): void
    {
        $configData = [
            'app_key' => 'test_key',
            'app_secret' => 'test_secret',
            'cache' => [
                'driver' => 'memory',
            ],
        ];

        $config = new ConfigManager($configData);
        $this->assertEquals($configData, $config->all());
    }

    public function testRequiredConfigurationMissing(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Required configuration key is missing: app_key');

        new ConfigManager([
            'app_secret' => 'test_secret',
        ]);
    }

    public function testMultipleRequiredConfigurationMissing(): void
    {
        $this->expectException(ConfigException::class);

        new ConfigManager([]);
    }

    public function testValidateRequiredKeys(): void
    {
        // 这应该不抛出异常
        $config = new ConfigManager([
            'app_key' => 'test_key',
            'app_secret' => 'test_secret',
        ]);

        $this->assertInstanceOf(ConfigManager::class, $config);
    }

    public function testMergeConfiguration(): void
    {
        $config = new ConfigManager([
            'app_key' => 'test_key',
            'app_secret' => 'test_secret',
            'cache' => [
                'driver' => 'memory',
                'prefix' => 'test_',
            ],
        ]);

        $config->merge([
            'cache' => [
                'default_ttl' => 3600,
                'prefix' => 'new_prefix_',
            ],
            'new_key' => 'new_value',
        ]);

        $this->assertEquals('new_prefix_', $config->get('cache.prefix'));
        $this->assertEquals(3600, $config->get('cache.default_ttl'));
        $this->assertEquals('memory', $config->get('cache.driver')); // 应该保留原值
        $this->assertEquals('new_value', $config->get('new_key'));
    }
}