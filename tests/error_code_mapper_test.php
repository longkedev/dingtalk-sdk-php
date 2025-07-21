<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Exceptions/DingTalkException.php';
require_once __DIR__ . '/../src/Exceptions/ApiException.php';
require_once __DIR__ . '/../src/Exceptions/AuthException.php';
require_once __DIR__ . '/../src/Exceptions/ConfigException.php';
require_once __DIR__ . '/../src/Exceptions/ContainerException.php';
require_once __DIR__ . '/../src/Exceptions/NetworkException.php';
require_once __DIR__ . '/../src/Exceptions/RateLimitException.php';
require_once __DIR__ . '/../src/Exceptions/ValidationException.php';
require_once __DIR__ . '/../src/Exceptions/ErrorCodes.php';
require_once __DIR__ . '/../src/Exceptions/ErrorMessageTranslator.php';
require_once __DIR__ . '/../src/Exceptions/ErrorContextCollector.php';
require_once __DIR__ . '/../src/Exceptions/ErrorCodeMapper.php';

use DingTalk\Exceptions\ErrorCodeMapper;
use DingTalk\Exceptions\ErrorCodes;
use DingTalk\Exceptions\ErrorMessageTranslator;
use DingTalk\Exceptions\ErrorContextCollector;

/**
 * 错误码映射器测试
 */
class ErrorCodeMapperTest
{
    private int $totalTests = 0;
    private int $passedTests = 0;
    private array $failedTests = [];

    public function runAllTests(): void
    {
        echo "=== 错误码映射器测试开始 ===\n\n";

        $this->testErrorCodeMapping();
        $this->testErrorCodes();
        $this->testMessageTranslator();
        $this->testContextCollector();
        $this->testIntegration();

        $this->printResults();
    }

    /**
     * 测试错误码映射功能
     */
    private function testErrorCodeMapping(): void
    {
        echo "--- 测试错误码映射功能 ---\n";

        $mapper = new ErrorCodeMapper('zh');

        // 测试V2 API错误码映射
        $this->test('V2认证错误码映射', function() use ($mapper) {
            $result = $mapper->mapErrorCode('40001', 'v2');
            return $result['code'] === '40001' && 
                   $result['type'] === 'auth' && 
                   $result['api_version'] === 'v2' &&
                   strpos($result['message'], '访问令牌') !== false;
        });

        // 测试V1 API错误码映射
        $this->test('V1认证错误码映射', function() use ($mapper) {
            $result = $mapper->mapErrorCode('40001', 'v1');
            return $result['code'] === '40001' && 
                   $result['type'] === 'auth' && 
                   $result['api_version'] === 'v1' &&
                   strpos($result['message'], '凭证') !== false;
        });

        // 测试自定义错误码映射
        $this->test('自定义错误码映射', function() use ($mapper) {
            $result = $mapper->mapErrorCode('SDK_001', 'custom');
            return $result['code'] === 'SDK_001' && 
                   $result['type'] === 'config' &&
                   strpos($result['message'], '配置文件') !== false;
        });

        // 测试未知错误码
        $this->test('未知错误码处理', function() use ($mapper) {
            $result = $mapper->mapErrorCode('UNKNOWN_CODE');
            return $result['type'] === 'unknown' &&
                   strpos($result['message'], '未知错误') !== false;
        });

        // 测试异常创建
        $this->test('异常实例创建', function() use ($mapper) {
            $exception = $mapper->createException('40001', 'v2');
            return $exception instanceof \DingTalk\Exceptions\AuthException &&
                   $exception->getErrorCode() === '40001';
        });

        echo "\n";
    }

    /**
     * 测试错误码常量
     */
    private function testErrorCodes(): void
    {
        echo "--- 测试错误码常量 ---\n";

        // 测试错误码类型检查
        $this->test('认证错误码检查', function() {
            return ErrorCodes::isAuthError(ErrorCodes::V2_AUTH_INVALID_ACCESS_TOKEN) &&
                   ErrorCodes::isAuthError(ErrorCodes::V1_AUTH_INVALID_CREDENTIAL);
        });

        $this->test('API错误码检查', function() {
            return ErrorCodes::isApiError(ErrorCodes::V2_API_INVALID_PARAMETER) &&
                   ErrorCodes::isApiError(ErrorCodes::V1_API_USER_NOT_EXIST);
        });

        $this->test('限流错误码检查', function() {
            return ErrorCodes::isRateLimitError(ErrorCodes::V2_RATE_LIMIT_EXCEEDED) &&
                   ErrorCodes::isRateLimitError(ErrorCodes::V1_RATE_LIMIT_EXCEEDED);
        });

        // 测试API版本检测
        $this->test('API版本检测', function() {
            return ErrorCodes::getApiVersion('40001') === 'v2' &&
                   ErrorCodes::getApiVersion('40013') === 'v1' &&
                   ErrorCodes::getApiVersion('SDK_001') === 'custom';
        });

        // 测试错误类型获取
        $this->test('错误类型获取', function() {
            return ErrorCodes::getErrorType('40001') === 'auth' &&
                   ErrorCodes::getErrorType('40015') === 'api' &&
                   ErrorCodes::getErrorType('90018') === 'rate_limit';
        });

        echo "\n";
    }

    /**
     * 测试消息翻译器
     */
    private function testMessageTranslator(): void
    {
        echo "--- 测试消息翻译器 ---\n";

        $translator = new ErrorMessageTranslator('zh');

        // 测试消息翻译
        $this->test('中文消息翻译', function() use ($translator) {
            $message = $translator->translateMessage('auth.invalid_access_token');
            return strpos($message, '访问令牌') !== false;
        });

        $this->test('英文消息翻译', function() use ($translator) {
            $translator->setLanguage('en');
            $message = $translator->translateMessage('auth.invalid_access_token');
            return strpos($message, 'access token') !== false;
        });

        // 测试恢复建议翻译
        $this->test('恢复建议翻译', function() use ($translator) {
            $translator->setLanguage('zh');
            $recovery = $translator->translateRecovery('auth.refresh_access_token');
            return strpos($recovery, '刷新') !== false;
        });

        // 测试不存在的键
        $this->test('不存在键的处理', function() use ($translator) {
            $message = $translator->translateMessage('non.existent.key');
            return strpos($message, '未知错误') !== false;
        });

        // 测试支持的语言
        $this->test('支持的语言列表', function() use ($translator) {
            $languages = $translator->getSupportedLanguages();
            return in_array('en', $languages) && 
                   in_array('zh', $languages) && 
                   in_array('ja', $languages);
        });

        echo "\n";
    }

    /**
     * 测试上下文收集器
     */
    private function testContextCollector(): void
    {
        echo "--- 测试上下文收集器 ---\n";

        $collector = new ErrorContextCollector(false);

        // 测试基础上下文
        $this->test('基础上下文收集', function() use ($collector) {
            $context = $collector->getContext();
            return isset($context['timestamp']) && 
                   isset($context['system']) && 
                   isset($context['php']) && 
                   isset($context['sdk']);
        });

        // 测试HTTP上下文
        $this->test('HTTP上下文收集', function() use ($collector) {
            $collector->addHttpContext([
                'method' => 'POST',
                'url' => 'https://api.dingtalk.com/v1.0/oauth2/accessToken',
                'headers' => ['Content-Type' => 'application/json']
            ]);
            $context = $collector->getContextByType('http');
            return $context['method'] === 'POST' && 
                   isset($context['url']) && 
                   isset($context['headers']);
        });

        // 测试API上下文
        $this->test('API上下文收集', function() use ($collector) {
            $collector->addApiContext([
                'endpoint' => '/v1.0/oauth2/accessToken',
                'version' => 'v2',
                'method' => 'POST',
                'response_code' => 200
            ]);
            $context = $collector->getContextByType('api');
            return $context['version'] === 'v2' && 
                   $context['response_code'] === 200;
        });

        // 测试敏感信息处理
        $this->test('敏感信息处理', function() use ($collector) {
            $collector->addAuthContext([
                'app_key' => 'test_key',
                'app_secret' => 'test_secret',
                'access_token' => 'test_token'
            ]);
            $context = $collector->getContextByType('auth');
            return $context['app_key'] === '[HIDDEN]';
        });

        // 测试JSON导出
        $this->test('JSON导出功能', function() use ($collector) {
            $json = $collector->toJson();
            $decoded = json_decode($json, true);
            return json_last_error() === JSON_ERROR_NONE && 
                   isset($decoded['timestamp']);
        });

        echo "\n";
    }

    /**
     * 测试集成功能
     */
    private function testIntegration(): void
    {
        echo "--- 测试集成功能 ---\n";

        $mapper = new ErrorCodeMapper('zh', false);

        // 测试完整的错误处理流程
        $this->test('完整错误处理流程', function() use ($mapper) {
            // 添加各种上下文
            $mapper->addHttpContext([
                'method' => 'POST',
                'url' => 'https://api.dingtalk.com/v1.0/oauth2/accessToken'
            ]);
            
            $mapper->addApiContext([
                'endpoint' => '/v1.0/oauth2/accessToken',
                'version' => 'v2'
            ]);
            
            $mapper->addAuthContext([
                'app_type' => 'internal',
                'corp_id' => 'test_corp'
            ]);

            // 收集错误上下文
            $context = $mapper->collectErrorContext('40001', ['custom_field' => 'custom_value']);
            
            // 创建异常
            $exception = $mapper->createException('40001', 'v2');
            
            return isset($context['error']) && 
                   isset($context['http']) && 
                   isset($context['api']) && 
                   isset($context['auth']) && 
                   isset($context['custom']['custom_field']) &&
                   $exception instanceof \DingTalk\Exceptions\AuthException;
        });

        // 测试错误恢复建议
        $this->test('错误恢复建议', function() use ($mapper) {
            $suggestion = $mapper->getRecoverySuggestion('40001', 'v2');
            return !empty($suggestion) && strpos($suggestion, '刷新') !== false;
        });

        // 测试错误类型判断
        $this->test('错误类型判断', function() use ($mapper) {
            return $mapper->isAuthError('40001', 'v2') &&
                   $mapper->isRateLimitError('90018', 'v2') &&
                   $mapper->isNetworkError('50001', 'v2');
        });

        // 测试支持的错误码获取
        $this->test('支持的错误码获取', function() use ($mapper) {
            $v2Codes = $mapper->getSupportedErrorCodes('v2');
            $v1Codes = $mapper->getSupportedErrorCodes('v1');
            $allCodes = $mapper->getSupportedErrorCodes('all');
            
            return count($v2Codes) > 0 && 
                   count($v1Codes) > 0 && 
                   count($allCodes) > count($v2Codes);
        });

        echo "\n";
    }

    /**
     * 执行单个测试
     */
    private function test(string $name, callable $testFunction): void
    {
        $this->totalTests++;
        
        try {
            $result = $testFunction();
            if ($result) {
                echo "✅ {$name}\n";
                $this->passedTests++;
            } else {
                echo "❌ {$name}\n";
                $this->failedTests[] = $name;
            }
        } catch (Exception $e) {
            echo "❌ {$name} - 异常: {$e->getMessage()}\n";
            $this->failedTests[] = $name . ' (异常: ' . $e->getMessage() . ')';
        }
    }

    /**
     * 打印测试结果
     */
    private function printResults(): void
    {
        echo "=== 测试结果 ===\n";
        echo "总测试数: {$this->totalTests}\n";
        echo "通过测试: {$this->passedTests}\n";
        echo "失败测试: " . count($this->failedTests) . "\n";
        echo "成功率: " . round(($this->passedTests / $this->totalTests) * 100, 2) . "%\n";

        if (!empty($this->failedTests)) {
            echo "\n失败的测试:\n";
            foreach ($this->failedTests as $test) {
                echo "- {$test}\n";
            }
        }

        echo "\n=== 错误码映射器测试完成 ===\n";
    }
}

// 运行测试
$test = new ErrorCodeMapperTest();
$test->runAllTests();