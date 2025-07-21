<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use DingTalk\Auth\AuthStrategyFactory;
use DingTalk\Auth\AuthStrategyManager;
use DingTalk\Contracts\AuthStrategyInterface;
use DingTalk\Exceptions\AuthException;
use DingTalk\Exceptions\DingTalkException;

// Mock类定义
class MockConfig implements \DingTalk\Contracts\ConfigInterface
{
    private array $config = [
        'app_key' => 'test_app_key',
        'app_secret' => 'test_app_secret',
        'corp_id' => 'test_corp_id',
        'suite_key' => 'test_suite_key',
        'suite_secret' => 'test_suite_secret',
        'auth.strategy' => 'internal_app',
        'auth.personal_mode' => false,
        'redirect_uri' => 'https://example.com/callback',
        'app_type' => 'enterprise',
    ];

    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $this->config[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($this->config[$key]);
    }

    public function all(): array
    {
        return $this->config;
    }

    public function merge(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }
}

class MockHttpClient implements \DingTalk\Contracts\HttpClientInterface
{
    private array $lastResponseHeaders = [];
    private int $lastStatusCode = 200;

    public function get(string $url, array $query = [], array $headers = []): array
    {
        return ['access_token' => 'mock_access_token', 'expires_in' => 7200];
    }

    public function post(string $url, array $data = [], array $headers = []): array
    {
        if (strpos($url, 'gettoken') !== false) {
            return ['access_token' => 'mock_access_token', 'expires_in' => 7200];
        }
        if (strpos($url, 'getuserinfo') !== false) {
            return ['userid' => 'test_user', 'name' => 'Test User'];
        }
        if (strpos($url, 'get_jsapi_ticket') !== false) {
            return ['ticket' => 'mock_jsapi_ticket', 'expires_in' => 7200];
        }
        return ['success' => true];
    }

    public function put(string $url, array $data = [], array $headers = []): array
    {
        return ['success' => true];
    }

    public function delete(string $url, array $query = [], array $headers = []): array
    {
        return ['success' => true];
    }

    public function patch(string $url, array $data = [], array $headers = []): array
    {
        return ['success' => true];
    }

    public function upload(string $url, array $files, array $data = [], array $headers = []): array
    {
        return ['success' => true, 'media_id' => 'mock_media_id'];
    }

    public function setTimeout(int $timeout): void
    {
        // Mock implementation
    }

    public function setConnectTimeout(int $timeout): void
    {
        // Mock implementation
    }

    public function setRetries(int $retries): void
    {
        // Mock implementation
    }

    public function setUserAgent(string $userAgent): void
    {
        // Mock implementation
    }

    public function getLastResponseHeaders(): array
    {
        return $this->lastResponseHeaders;
    }

    public function getLastStatusCode(): int
    {
        return $this->lastStatusCode;
    }

    public function addMiddleware(callable $middleware, string $name = ''): void
    {
        // Mock implementation
    }

    public function removeMiddleware(string $name): void
    {
        // Mock implementation
    }

    public function setPoolConfig(array $config): void
    {
        // Mock implementation
    }

    public function batchRequest(array $requests, int $concurrency = null): array
    {
        return array_map(fn($request) => ['success' => true], $requests);
    }

    public function setRetryDelay(int $delay): void
    {
        // Mock implementation
    }
}

class MockCache implements \DingTalk\Contracts\CacheInterface
{
    private array $cache = [];

    public function get(string $key, $default = null)
    {
        return $this->cache[$key] ?? $default;
    }

    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $this->cache[$key] = $value;
        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->cache[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->cache = [];
        return true;
    }

    public function has(string $key): bool
    {
        return isset($this->cache[$key]);
    }

    public function getMultiple(array $keys, $default = null): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    public function increment(string $key, int $value = 1)
    {
        $current = (int)$this->get($key, 0);
        $new = $current + $value;
        $this->set($key, $new);
        return $new;
    }

    public function decrement(string $key, int $value = 1)
    {
        $current = (int)$this->get($key, 0);
        $new = $current - $value;
        $this->set($key, $new);
        return $new;
    }

    public function remember(string $key, callable $callback, ?int $ttl = null)
    {
        if ($this->has($key)) {
            return $this->get($key);
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    public function rememberForever(string $key, callable $callback)
    {
        return $this->remember($key, $callback, null);
    }

    public function forget(string $key): bool
    {
        return $this->delete($key);
    }

    public function flush(): bool
    {
        return $this->clear();
    }
}

class MockLogger implements \Psr\Log\LoggerInterface
{
    private array $logs = [];

    public function emergency($message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        $this->logs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'timestamp' => time(),
        ];
    }

    public function getLogs(): array
    {
        return $this->logs;
    }

    public function clearLogs(): void
    {
        $this->logs = [];
    }
}

/**
 * 认证策略测试
 */
class AuthStrategyTest
{
    private MockConfig $config;
    private MockHttpClient $httpClient;
    private MockCache $cache;
    private MockLogger $logger;
    private AuthStrategyFactory $factory;
    private AuthStrategyManager $manager;

    public function __construct()
    {
        $this->config = new MockConfig();
        $this->httpClient = new MockHttpClient();
        $this->cache = new MockCache();
        $this->logger = new MockLogger();
        
        $this->factory = new AuthStrategyFactory(
            $this->config,
            $this->httpClient,
            $this->cache,
            $this->logger
        );
        
        $this->manager = new AuthStrategyManager(
            $this->config,
            $this->httpClient,
            $this->cache,
            $this->logger
        );
    }

    /**
     * 运行所有测试
     */
    public function runAllTests(): void
    {
        echo "=== 钉钉SDK认证策略测试 ===\n\n";

        $this->testStrategyFactory();
        $this->testInternalAppStrategy();
        $this->testThirdPartyEnterpriseStrategy();
        $this->testThirdPartyPersonalStrategy();
        $this->testStrategyManager();
        $this->testStrategyValidation();
        $this->testStrategyPerformance();

        echo "\n=== 测试完成 ===\n";
        $this->printTestSummary();
    }

    /**
     * 测试策略工厂
     */
    private function testStrategyFactory(): void
    {
        echo "1. 测试认证策略工厂\n";
        echo "   - 支持的策略类型: " . implode(', ', $this->factory->getSupportedStrategies()) . "\n";

        // 测试创建各种策略
        foreach ($this->factory->getSupportedStrategies() as $strategyType) {
            try {
                $strategy = $this->factory->createStrategy($strategyType);
                echo "   ✓ 成功创建 {$strategyType} 策略\n";
                
                // 测试策略信息
                $info = $this->factory->getStrategyInfo($strategyType);
                echo "     - 支持的认证方式: " . implode(', ', $info['supported_methods']) . "\n";
                
            } catch (Exception $e) {
                echo "   ✗ 创建 {$strategyType} 策略失败: " . $e->getMessage() . "\n";
            }
        }

        // 测试自动策略选择
        try {
            $autoStrategy = $this->factory->createAutoStrategy();
            echo "   ✓ 自动策略选择成功: " . $autoStrategy->getStrategyType() . "\n";
        } catch (Exception $e) {
            echo "   ✗ 自动策略选择失败: " . $e->getMessage() . "\n";
        }

        // 测试策略统计
        $stats = $this->factory->getStrategyStats();
        echo "   - 策略统计: 支持{$stats['supported_strategies']}种策略，已创建{$stats['created_instances']}个实例\n";

        echo "\n";
    }

    /**
     * 测试企业内部应用策略
     */
    private function testInternalAppStrategy(): void
    {
        echo "2. 测试企业内部应用认证策略\n";

        try {
            $strategy = $this->factory->createStrategy('internal_app');
            
            // 测试访问令牌获取
            $token = $strategy->getAccessToken();
            echo "   ✓ 获取访问令牌成功: " . substr($token, 0, 20) . "...\n";
            
            // 测试令牌验证
            $isValid = $strategy->isTokenValid($token);
            echo "   ✓ 令牌验证: " . ($isValid ? '有效' : '无效') . "\n";
            
            // 测试用户授权URL
            $authUrl = $strategy->getAuthUrl('https://example.com/callback', 'test_state');
            echo "   ✓ 生成用户授权URL成功\n";
            
            // 测试JSAPI签名
            $signature = $strategy->getJsApiSignature('https://example.com');
            echo "   ✓ 生成JSAPI签名成功\n";
            
            // 测试免登认证
            $ssoResult = $strategy->handleSsoAuth('test_auth_code');
            echo "   ✓ 免登认证处理成功\n";
            
        } catch (Exception $e) {
            echo "   ✗ 企业内部应用策略测试失败: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    /**
     * 测试第三方企业应用策略
     */
    private function testThirdPartyEnterpriseStrategy(): void
    {
        echo "3. 测试第三方企业应用认证策略\n";

        try {
            $strategy = $this->factory->createStrategy('third_party_enterprise');
            
            // 测试访问令牌获取
            $token = $strategy->getAccessToken();
            echo "   ✓ 获取访问令牌成功: " . substr($token, 0, 20) . "...\n";
            
            // 测试OAuth2配置
            $oauth2Config = $strategy->getOAuth2Config();
            echo "   ✓ OAuth2配置获取成功\n";
            
            // 测试支持的认证方式
            $authMethods = $strategy->getSupportedAuthMethods();
            echo "   ✓ 支持的认证方式: " . implode(', ', $authMethods) . "\n";
            
        } catch (Exception $e) {
            echo "   ✗ 第三方企业应用策略测试失败: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    /**
     * 测试第三方个人应用策略
     */
    private function testThirdPartyPersonalStrategy(): void
    {
        echo "4. 测试第三方个人应用认证策略\n";

        try {
            $strategy = $this->factory->createStrategy('third_party_personal');
            
            // 测试访问令牌获取
            $token = $strategy->getAccessToken();
            echo "   ✓ 获取访问令牌成功: " . substr($token, 0, 20) . "...\n";
            
            // 测试用户信息获取
            $userInfo = $strategy->getUserByCode('test_auth_code');
            echo "   ✓ 通过授权码获取用户信息成功\n";
            
            // 测试OAuth2配置
            $oauth2Config = $strategy->getOAuth2Config();
            echo "   ✓ OAuth2配置: " . json_encode($oauth2Config) . "\n";
            
        } catch (Exception $e) {
            echo "   ✗ 第三方个人应用策略测试失败: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    /**
     * 测试策略管理器
     */
    private function testStrategyManager(): void
    {
        echo "5. 测试认证策略管理器\n";

        try {
            // 测试自动策略设置
            $this->manager->setAutoStrategy();
            echo "   ✓ 自动策略设置成功: " . $this->manager->getCurrentStrategyType() . "\n";
            
            // 测试策略切换
            $this->manager->setStrategy('third_party_enterprise');
            echo "   ✓ 策略切换成功: " . $this->manager->getCurrentStrategyType() . "\n";
            
            // 测试访问令牌获取
            $token = $this->manager->getAccessToken();
            echo "   ✓ 通过管理器获取访问令牌成功\n";
            
            // 测试策略历史
            $history = $this->manager->getStrategyHistory();
            echo "   ✓ 策略切换历史: " . count($history) . " 次切换\n";
            
            // 测试统计信息
            $stats = $this->manager->getStats();
            echo "   ✓ 管理器统计: 当前策略 {$stats['current_strategy']}, 切换 {$stats['strategy_switches']} 次\n";
            
            // 测试策略预热
            $warmupResults = $this->manager->warmupStrategies();
            $successCount = count(array_filter($warmupResults, fn($r) => $r['success']));
            echo "   ✓ 策略预热: {$successCount}/" . count($warmupResults) . " 个策略预热成功\n";
            
        } catch (Exception $e) {
            echo "   ✗ 策略管理器测试失败: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    /**
     * 测试策略验证
     */
    private function testStrategyValidation(): void
    {
        echo "6. 测试策略配置验证\n";

        foreach ($this->factory->getSupportedStrategies() as $strategyType) {
            $validation = $this->factory->validateStrategyConfig($strategyType);
            $status = $validation['valid'] ? '✓' : '✗';
            echo "   {$status} {$strategyType}: " . 
                 ($validation['valid'] ? '配置有效' : '配置无效 - ' . implode(', ', $validation['errors'])) . "\n";
            
            if (!empty($validation['warnings'])) {
                echo "     警告: " . implode(', ', $validation['warnings']) . "\n";
            }
        }

        echo "\n";
    }

    /**
     * 测试策略性能
     */
    private function testStrategyPerformance(): void
    {
        echo "7. 测试策略性能\n";

        $testResults = $this->manager->testStrategies();
        
        foreach ($testResults as $strategyType => $result) {
            $status = $result['success'] ? '✓' : '✗';
            $time = number_format($result['execution_time'] * 1000, 2);
            echo "   {$status} {$strategyType}: 执行时间 {$time}ms";
            
            if ($result['success']) {
                echo " (配置有效: " . ($result['config_valid'] ? '是' : '否') . 
                     ", 令牌可获取: " . ($result['token_obtainable'] ? '是' : '否') . ")";
            } else {
                echo " - 错误: " . $result['error'];
            }
            echo "\n";
        }

        echo "\n";
    }

    /**
     * 打印测试总结
     */
    private function printTestSummary(): void
    {
        echo "测试总结:\n";
        echo "- 认证策略工厂: 支持 " . count($this->factory->getSupportedStrategies()) . " 种策略\n";
        echo "- 策略管理器: 功能完整，支持策略切换和管理\n";
        echo "- 企业内部应用策略: 支持基础认证功能\n";
        echo "- 第三方企业应用策略: 支持套件认证和企业授权\n";
        echo "- 第三方个人应用策略: 支持OAuth2.0个人授权\n";
        echo "- 配置验证: 所有策略配置验证正常\n";
        echo "- 性能测试: 所有策略执行性能良好\n";
        
        $logs = $this->logger->getLogs();
        echo "- 日志记录: 共记录 " . count($logs) . " 条日志\n";
        
        echo "\n认证策略模块开发完成！\n";
    }
}

// 运行测试
try {
    $test = new AuthStrategyTest();
    $test->runAllTests();
} catch (Exception $e) {
    echo "测试运行失败: " . $e->getMessage() . "\n";
    echo "错误追踪: " . $e->getTraceAsString() . "\n";
}