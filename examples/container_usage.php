<?php

declare(strict_types=1);

/**
 * 服务容器使用示例
 * 
 * 演示如何使用DingTalk SDK的服务容器功能
 */

require_once __DIR__ . '/../vendor/autoload.php';

use DingTalk\Container\Container;
use DingTalk\Container\Providers\ConfigServiceProvider;
use DingTalk\Contracts\ConfigInterface;

// 创建容器实例
$container = new Container();

// 注册服务提供者
$container->register(ConfigServiceProvider::class);

// 启动所有服务提供者
$container->boot();

// 基本绑定示例
$container->bind('logger', function () {
    return new class {
        public function log($message) {
            echo "Log: {$message}\n";
        }
    };
});

// 单例绑定示例
$container->singleton('cache', function () {
    return new class {
        private $data = [];
        
        public function get($key) {
            return $this->data[$key] ?? null;
        }
        
        public function set($key, $value) {
            $this->data[$key] = $value;
        }
    };
});

// 实例绑定示例
$container->instance('app.name', 'DingTalk SDK');

// 服务标记示例
$container->tag(['logger', 'cache'], 'utilities');

// 解析服务
$logger = $container->get('logger');
$cache = $container->get('cache');
$config = $container->get(ConfigInterface::class);

// 使用服务
$logger->log('Container example started');
$cache->set('test', 'value');

echo "App Name: " . $container->get('app.name') . "\n";
echo "Cache Test: " . $cache->get('test') . "\n";

// 获取标记的服务
$utilities = $container->tagged('utilities');
echo "Tagged utilities count: " . count($utilities) . "\n";

// 方法调用示例
$result = $container->call(function ($logger, $cache) {
    $logger->log('Called with dependency injection');
    return 'Method called successfully';
}, []);

echo "Call result: {$result}\n";

echo "Container example completed successfully!\n";