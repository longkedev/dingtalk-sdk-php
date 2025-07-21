<?php
/**
 * 简单的异常类测试脚本
 * 不依赖PHPUnit，直接运行测试
 */

// 自动加载
require_once __DIR__ . '/../src/Exceptions/DingTalkException.php';
require_once __DIR__ . '/../src/Exceptions/ApiException.php';
require_once __DIR__ . '/../src/Exceptions/AuthException.php';
require_once __DIR__ . '/../src/Exceptions/ConfigException.php';
require_once __DIR__ . '/../src/Exceptions/ContainerException.php';
require_once __DIR__ . '/../src/Exceptions/NetworkException.php';
require_once __DIR__ . '/../src/Exceptions/ValidationException.php';
require_once __DIR__ . '/../src/Exceptions/RateLimitException.php';

use DingTalk\Exceptions\DingTalkException;
use DingTalk\Exceptions\ApiException;
use DingTalk\Exceptions\AuthException;
use DingTalk\Exceptions\ConfigException;
use DingTalk\Exceptions\ContainerException;
use DingTalk\Exceptions\NetworkException;
use DingTalk\Exceptions\ValidationException;
use DingTalk\Exceptions\RateLimitException;

class SimpleTestRunner
{
    private $tests = 0;
    private $passed = 0;
    private $failed = 0;

    public function assert($condition, $message)
    {
        $this->tests++;
        if ($condition) {
            $this->passed++;
            echo "✓ {$message}\n";
        } else {
            $this->failed++;
            echo "✗ {$message}\n";
        }
    }

    public function assertEquals($expected, $actual, $message)
    {
        $this->assert($expected === $actual, $message . " (期望: {$expected}, 实际: {$actual})");
    }

    public function assertTrue($condition, $message)
    {
        $this->assert($condition === true, $message);
    }

    public function assertInstanceOf($expected, $actual, $message)
    {
        $this->assert($actual instanceof $expected, $message);
    }

    public function summary()
    {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "测试总结:\n";
        echo "总测试数: {$this->tests}\n";
        echo "通过: {$this->passed}\n";
        echo "失败: {$this->failed}\n";
        echo "成功率: " . round(($this->passed / $this->tests) * 100, 2) . "%\n";
        echo str_repeat("=", 50) . "\n";
        
        return $this->failed === 0;
    }
}

$test = new SimpleTestRunner();

echo "开始测试钉钉SDK异常类...\n\n";

// 测试 DingTalkException 基础功能
echo "测试 DingTalkException 基础功能:\n";
$exception = new DingTalkException('测试消息', 'TEST_CODE', ['key' => 'value'], 500);
$test->assertEquals('测试消息', $exception->getMessage(), 'getMessage()');
$test->assertEquals('TEST_CODE', $exception->getErrorCode(), 'getErrorCode()');
$test->assertEquals(['key' => 'value'], $exception->getErrorDetails(), 'getErrorDetails()');
$test->assertEquals(500, $exception->getCode(), 'getCode()');

// 测试 toArray 方法
$array = $exception->toArray();
$test->assertTrue(isset($array['message']), 'toArray() 包含 message');
$test->assertTrue(isset($array['error_code']), 'toArray() 包含 error_code');
$test->assertTrue(isset($array['error_details']), 'toArray() 包含 error_details');

// 测试 toJson 方法
$json = $exception->toJson();
$test->assertTrue(is_string($json), 'toJson() 返回字符串');
$decoded = json_decode($json, true);
$test->assertTrue(is_array($decoded), 'toJson() 返回有效JSON');

echo "\n";

// 测试 ApiException
echo "测试 ApiException:\n";
$apiException = new ApiException('API错误', 'API_ERROR', ['endpoint' => '/test'], 400);
$apiException->setRequestId('req-123');
$apiException->setResponseData(['error' => 'not found']);

$test->assertInstanceOf(DingTalkException::class, $apiException, 'ApiException 继承自 DingTalkException');
$test->assertEquals('req-123', $apiException->getRequestId(), 'getRequestId()');
$test->assertEquals(['error' => 'not found'], $apiException->getResponseData(), 'getResponseData()');

echo "\n";

// 测试 AuthException 静态方法
echo "测试 AuthException 静态方法:\n";
$authException = AuthException::invalidAccessToken('invalid_token');
$test->assertInstanceOf(AuthException::class, $authException, 'invalidAccessToken() 返回 AuthException');
$test->assertEquals('INVALID_ACCESS_TOKEN', $authException->getErrorCode(), 'invalidAccessToken() 错误代码');

$authException = AuthException::accessTokenExpired(time() - 3600);
$test->assertEquals('ACCESS_TOKEN_EXPIRED', $authException->getErrorCode(), 'accessTokenExpired() 错误代码');

$authException = AuthException::insufficientPermissions(['admin'], ['read']);
$test->assertEquals('INSUFFICIENT_PERMISSIONS', $authException->getErrorCode(), 'insufficientPermissions() 错误代码');
$test->assertEquals(['admin'], $authException->getMissingPermissions(), 'getMissingPermissions()');

echo "\n";

// 测试 ConfigException 静态方法
echo "测试 ConfigException 静态方法:\n";
$configException = ConfigException::configFileNotFound('/path/to/config.php');
$test->assertEquals('CONFIG_FILE_NOT_FOUND', $configException->getErrorCode(), 'configFileNotFound() 错误代码');
$test->assertEquals('/path/to/config.php', $configException->getConfigFile(), 'getConfigFile()');

$configException = ConfigException::missingConfigKey('database.host', '/config.php');
$test->assertEquals('MISSING_CONFIG_KEY', $configException->getErrorCode(), 'missingConfigKey() 错误代码');
$test->assertEquals('database.host', $configException->getConfigKey(), 'getConfigKey()');

echo "\n";

// 测试 ContainerException 静态方法
echo "测试 ContainerException 静态方法:\n";
$containerException = ContainerException::serviceNotFound('UserService');
$test->assertEquals('SERVICE_NOT_FOUND', $containerException->getErrorCode(), 'serviceNotFound() 错误代码');
$test->assertEquals('UserService', $containerException->getServiceId(), 'getServiceId()');

$containerException = ContainerException::circularDependency(['A', 'B', 'A']);
$test->assertEquals('CIRCULAR_DEPENDENCY', $containerException->getErrorCode(), 'circularDependency() 错误代码');
$test->assertEquals(['A', 'B', 'A'], $containerException->getDependencyChain(), 'getDependencyChain()');

echo "\n";

// 测试 NetworkException 静态方法
echo "测试 NetworkException 静态方法:\n";
$networkException = NetworkException::connectionTimeout(30);
$test->assertEquals('CONNECTION_TIMEOUT', $networkException->getErrorCode(), 'connectionTimeout() 错误代码');

$networkException = NetworkException::dnsResolutionFailed('api.dingtalk.com');
$test->assertEquals('DNS_RESOLUTION_FAILED', $networkException->getErrorCode(), 'dnsResolutionFailed() 错误代码');

echo "\n";

// 测试 ValidationException 静态方法
echo "测试 ValidationException 静态方法:\n";
$validationException = ValidationException::missingParameter('user_id');
$test->assertEquals('MISSING_PARAMETER', $validationException->getErrorCode(), 'missingParameter() 错误代码');

$validationException = ValidationException::invalidFormat('email', 'email格式', 'invalid-email');
$test->assertEquals('INVALID_FORMAT', $validationException->getErrorCode(), 'invalidFormat() 错误代码');

// 测试批量验证
$validationException = new ValidationException('验证失败');
$validationException->addFailedField('name', 'required');
$validationException->addFailedField('email', 'format');
$failedFields = $validationException->getFailedFields();
$test->assertEquals(['name' => 'required', 'email' => 'format'], $failedFields, 'addFailedField() 和 getFailedFields()');

echo "\n";

// 测试 RateLimitException 静态方法
echo "测试 RateLimitException 静态方法:\n";
$resetTime = time() + 3600;
$rateLimitException = RateLimitException::rateLimitExceeded(100, 100, $resetTime, 60);
$test->assertEquals('RATE_LIMIT_EXCEEDED', $rateLimitException->getErrorCode(), 'rateLimitExceeded() 错误代码');
$test->assertEquals(100, $rateLimitException->getCurrentRequests(), 'getCurrentRequests()');
$test->assertEquals(100, $rateLimitException->getMaxRequests(), 'getMaxRequests()');
$test->assertEquals(0, $rateLimitException->getRemainingRequests(), 'getRemainingRequests()');
$test->assertEquals($resetTime, $rateLimitException->getResetTime(), 'getResetTime()');
$test->assertTrue($rateLimitException->canRetry(), 'canRetry()');

$rateLimitException = RateLimitException::qpsLimit(10, 5, 1);
$test->assertEquals('QPS_LIMIT', $rateLimitException->getErrorCode(), 'qpsLimit() 错误代码');
$test->assertEquals('qps', $rateLimitException->getLimitType(), 'getLimitType()');

echo "\n";

// 测试继承关系
echo "测试继承关系:\n";
$test->assertInstanceOf(DingTalkException::class, new ApiException('test'), 'ApiException 继承自 DingTalkException');
$test->assertInstanceOf(DingTalkException::class, new AuthException('test'), 'AuthException 继承自 DingTalkException');
$test->assertInstanceOf(DingTalkException::class, new ConfigException('test'), 'ConfigException 继承自 DingTalkException');
$test->assertInstanceOf(DingTalkException::class, new ContainerException('test'), 'ContainerException 继承自 DingTalkException');
$test->assertInstanceOf(DingTalkException::class, new NetworkException('test'), 'NetworkException 继承自 DingTalkException');
$test->assertInstanceOf(DingTalkException::class, new ValidationException('test'), 'ValidationException 继承自 DingTalkException');
$test->assertInstanceOf(DingTalkException::class, new RateLimitException('test'), 'RateLimitException 继承自 DingTalkException');

echo "\n";

// 输出测试总结
$success = $test->summary();

if ($success) {
    echo "\n🎉 所有测试通过！异常类功能正常。\n";
    exit(0);
} else {
    echo "\n❌ 部分测试失败，请检查异常类实现。\n";
    exit(1);
}