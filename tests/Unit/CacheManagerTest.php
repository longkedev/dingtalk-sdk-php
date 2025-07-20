<?php

declare(strict_types=1);

namespace DingTalk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use DingTalk\Cache\CacheManager;
use DingTalk\Cache\Drivers\MemoryDriver;
use DingTalk\Config\ConfigManager;

/**
 * 缓存管理器单元测试
 */
class CacheManagerTest extends TestCase
{
    private CacheManager $cache;

    protected function setUp(): void
    {
        $driver = new MemoryDriver();
        $this->cache = new CacheManager($driver, 'test_', 3600);
    }

    public function testBasicCacheOperations(): void
    {
        // 测试设置和获取
        $this->cache->set('test_key', 'test_value', 3600);
        $this->assertEquals('test_value', $this->cache->get('test_key'));

        // 测试存在性检查
        $this->assertTrue($this->cache->has('test_key'));
        $this->assertFalse($this->cache->has('nonexistent_key'));

        // 测试删除
        $this->cache->delete('test_key');
        $this->assertFalse($this->cache->has('test_key'));
        $this->assertNull($this->cache->get('test_key'));
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->cache->get('nonexistent_key'));
        $this->assertEquals('default', $this->cache->get('nonexistent_key', 'default'));
    }

    public function testMultipleOperations(): void
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        // 测试批量设置
        $this->cache->setMultiple($data, 3600);

        // 测试批量获取
        $result = $this->cache->getMultiple(['key1', 'key2', 'key3']);
        $this->assertEquals($data, $result);

        // 测试部分获取
        $partial = $this->cache->getMultiple(['key1', 'nonexistent', 'key3'], 'default');
        $expected = [
            'key1' => 'value1',
            'nonexistent' => 'default',
            'key3' => 'value3',
        ];
        $this->assertEquals($expected, $partial);

        // 测试批量删除
        $this->cache->deleteMultiple(['key1', 'key3']);
        $this->assertFalse($this->cache->has('key1'));
        $this->assertTrue($this->cache->has('key2'));
        $this->assertFalse($this->cache->has('key3'));
    }

    public function testRememberFunction(): void
    {
        $callCount = 0;
        $callback = function () use (&$callCount) {
            $callCount++;
            return 'computed_value_' . $callCount;
        };

        // 第一次调用应该执行回调
        $result1 = $this->cache->remember('computed_key', $callback, 3600);
        $this->assertEquals('computed_value_1', $result1);
        $this->assertEquals(1, $callCount);

        // 第二次调用应该从缓存获取
        $result2 = $this->cache->remember('computed_key', $callback, 3600);
        $this->assertEquals('computed_value_1', $result2);
        $this->assertEquals(1, $callCount); // 回调不应该再次执行

        // 删除缓存后再次调用应该执行回调
        $this->cache->delete('computed_key');
        $result3 = $this->cache->remember('computed_key', $callback, 3600);
        $this->assertEquals('computed_value_2', $result3);
        $this->assertEquals(2, $callCount);
    }

    public function testClearCache(): void
    {
        // 设置一些缓存数据
        $this->cache->setMultiple([
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ], 3600);

        $this->assertTrue($this->cache->has('key1'));
        $this->assertTrue($this->cache->has('key2'));
        $this->assertTrue($this->cache->has('key3'));

        // 清空缓存
        $this->cache->clear();

        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
        $this->assertFalse($this->cache->has('key3'));
    }

    public function testCachePrefix(): void
    {
        // 获取底层驱动来检查前缀
        $driver = $this->cache->getDriver();
        $this->assertInstanceOf(MemoryDriver::class, $driver);

        // 设置缓存
        $this->cache->set('test_key', 'test_value', 3600);

        // 检查驱动中的键是否包含前缀
        $keys = $driver->getKeys();
        $this->assertContains('test_test_key', $keys);
    }

    public function testCacheExpiration(): void
    {
        // 设置一个很短的过期时间
        $this->cache->set('expire_key', 'expire_value', 1);
        $this->assertTrue($this->cache->has('expire_key'));

        // 等待过期
        sleep(2);

        // 检查是否已过期
        $this->assertFalse($this->cache->has('expire_key'));
        $this->assertNull($this->cache->get('expire_key'));
    }

    public function testCacheStats(): void
    {
        $driver = $this->cache->getDriver();
        
        // 设置一些数据
        $this->cache->setMultiple([
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ], 3600);

        $stats = $driver->getStats();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_keys', $stats);
        $this->assertArrayHasKey('memory_usage', $stats);
        $this->assertEquals(3, $stats['total_keys']);
    }

    public function testCacheGarbageCollection(): void
    {
        $driver = $this->cache->getDriver();

        // 设置一些会过期的数据
        $this->cache->set('expire1', 'value1', 1);
        $this->cache->set('expire2', 'value2', 1);
        $this->cache->set('permanent', 'value3', 3600);

        $this->assertEquals(3, count($driver->getKeys()));

        // 等待过期
        sleep(2);

        // 执行垃圾回收
        $collected = $driver->gc();
        $this->assertEquals(2, $collected);

        // 检查剩余的键
        $this->assertEquals(1, count($driver->getKeys()));
        $this->assertTrue($this->cache->has('permanent'));
    }

    public function testCacheExportImport(): void
    {
        $driver = $this->cache->getDriver();

        // 设置一些数据
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];
        $this->cache->setMultiple($data, 3600);

        // 导出数据
        $exported = $driver->export();
        $this->assertIsArray($exported);

        // 清空缓存
        $this->cache->clear();
        $this->assertEquals(0, count($driver->getKeys()));

        // 导入数据
        $driver->import($exported);

        // 验证数据已恢复
        foreach ($data as $key => $value) {
            $this->assertEquals($value, $this->cache->get($key));
        }
    }

    protected function tearDown(): void
    {
        $this->cache->clear();
    }
}