<?php

declare(strict_types=1);

namespace DingTalk\Tests\Exceptions;

use DingTalk\Exceptions\{
    DingTalkException,
    ApiException,
    AuthException,
    ConfigException,
    ContainerException,
    NetworkException,
    ValidationException,
    RateLimitException
};
use PHPUnit\Framework\TestCase;

/**
 * 异常类测试
 */
class ExceptionTest extends TestCase
{
    public function testDingTalkExceptionBasicFunctionality(): void
    {
        $exception = new DingTalkException(
            'Test message',
            'TEST_CODE',
            ['key' => 'value'],
            500
        );
        
        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals('TEST_CODE', $exception->getErrorCode());
        $this->assertEquals(['key' => 'value'], $exception->getErrorDetails());
        $this->assertEquals(500, $exception->getCode());
        
        $array = $exception->toArray();
        $this->assertArrayHasKey('message', $array);
        $this->assertArrayHasKey('error_code', $array);
        $this->assertArrayHasKey('error_details', $array);
        
        $json = $exception->toJson();
        $this->assertJson($json);
    }
    
    public function testApiExceptionFunctionality(): void
    {
        $exception = new ApiException();
        $exception->setRequestId('req-123');
        $exception->setResponseData(['status' => 'error']);
        
        $this->assertEquals('req-123', $exception->getRequestId());
        $this->assertEquals(['status' => 'error'], $exception->getResponseData());
        
        $array = $exception->toArray();
        $this->assertArrayHasKey('request_id', $array);
        $this->assertArrayHasKey('response_data', $array);
    }
    
    public function testAuthExceptionStaticMethods(): void
    {
        $exception = AuthException::invalidAccessToken('token123');
        $this->assertEquals(AuthException::INVALID_ACCESS_TOKEN, $exception->getErrorCode());
        $this->assertEquals('access_token', $exception->getAuthType());
        $this->assertEquals(401, $exception->getCode());
        
        $exception = AuthException::accessTokenExpired(1234567890);
        $this->assertEquals(AuthException::ACCESS_TOKEN_EXPIRED, $exception->getErrorCode());
        
        $exception = AuthException::insufficientPermissions(['read', 'write'], ['read']);
        $this->assertEquals(['read', 'write'], $exception->getRequiredPermissions());
        $this->assertEquals(['read'], $exception->getCurrentPermissions());
        $this->assertEquals(['write'], $exception->getMissingPermissions());
    }
    
    public function testConfigExceptionStaticMethods(): void
    {
        $exception = ConfigException::configFileNotFound('/path/to/config.php');
        $this->assertEquals(ConfigException::CONFIG_FILE_NOT_FOUND, $exception->getErrorCode());
        $this->assertEquals('/path/to/config.php', $exception->getConfigFile());
        
        $exception = ConfigException::missingConfigKey('database.host');
        $this->assertEquals(ConfigException::MISSING_CONFIG_KEY, $exception->getErrorCode());
        $this->assertEquals('database.host', $exception->getConfigKey());
        
        $exception = ConfigException::invalidConfigValue('timeout', 'invalid', 'integer');
        $this->assertEquals(ConfigException::INVALID_CONFIG_VALUE, $exception->getErrorCode());
        $this->assertEquals('timeout', $exception->getConfigKey());
        $this->assertEquals('invalid', $exception->getConfigValue());
    }
    
    public function testContainerExceptionStaticMethods(): void
    {
        $exception = ContainerException::serviceNotFound('UserService');
        $this->assertEquals(ContainerException::SERVICE_NOT_FOUND, $exception->getErrorCode());
        $this->assertEquals('UserService', $exception->getServiceId());
        
        $exception = ContainerException::circularDependency(['A', 'B', 'C', 'A']);
        $this->assertEquals(ContainerException::CIRCULAR_DEPENDENCY, $exception->getErrorCode());
        $this->assertEquals(['A', 'B', 'C', 'A'], $exception->getDependencyChain());
        
        $exception = ContainerException::serviceInstantiationFailed('UserService', 'UserServiceImpl');
        $this->assertEquals('UserService', $exception->getServiceId());
        $this->assertEquals('UserServiceImpl', $exception->getServiceType());
    }
    
    public function testNetworkExceptionStaticMethods(): void
    {
        $exception = NetworkException::connectionTimeout(30);
        $this->assertEquals(NetworkException::CONNECTION_TIMEOUT, $exception->getErrorCode());
        $this->assertStringContains('30 seconds', $exception->getMessage());
        
        $exception = NetworkException::dnsResolutionFailed('api.dingtalk.com');
        $this->assertEquals(NetworkException::DNS_RESOLUTION_FAILED, $exception->getErrorCode());
        $this->assertStringContains('api.dingtalk.com', $exception->getMessage());
        
        $exception = NetworkException::connectionRefused('localhost', 8080);
        $this->assertEquals(NetworkException::CONNECTION_REFUSED, $exception->getErrorCode());
    }
    
    public function testValidationExceptionStaticMethods(): void
    {
        $exception = ValidationException::missingParameter('user_id');
        $this->assertEquals(ValidationException::MISSING_PARAMETER, $exception->getErrorCode());
        $this->assertEquals(['user_id' => 'required'], $exception->getFailedFields());
        
        $exception = ValidationException::invalidFormat('email', 'email format', 'invalid-email');
        $this->assertEquals(ValidationException::INVALID_FORMAT, $exception->getErrorCode());
        $this->assertArrayHasKey('email', $exception->getFailedFields());
        
        $exception = ValidationException::invalidValue('status', 'invalid', ['active', 'inactive']);
        $this->assertEquals(ValidationException::INVALID_VALUE, $exception->getErrorCode());
        
        $exception = ValidationException::invalidLength('password', 3, 8, 20);
        $this->assertEquals(ValidationException::INVALID_LENGTH, $exception->getErrorCode());
        
        $exception = ValidationException::invalidType('age', 'integer', 'string');
        $this->assertEquals(ValidationException::INVALID_TYPE, $exception->getErrorCode());
    }
    
    public function testRateLimitExceptionStaticMethods(): void
    {
        $resetTime = time() + 3600;
        $exception = RateLimitException::rateLimitExceeded(100, 100, $resetTime, 60);
        $this->assertEquals(RateLimitException::RATE_LIMIT_EXCEEDED, $exception->getErrorCode());
        $this->assertEquals(100, $exception->getCurrentRequests());
        $this->assertEquals(100, $exception->getMaxRequests());
        $this->assertEquals(0, $exception->getRemainingRequests());
        $this->assertEquals($resetTime, $exception->getResetTime());
        $this->assertEquals(60, $exception->getRetryAfter());
        $this->assertTrue($exception->canRetry());
        $this->assertEquals(429, $exception->getCode());
        
        $exception = RateLimitException::qpsLimit(10, 5, 1);
        $this->assertEquals(RateLimitException::QPS_LIMIT, $exception->getErrorCode());
        $this->assertEquals(RateLimitException::QPS_LIMIT, $exception->getLimitType());
        
        $exception = RateLimitException::quotaExceeded(1000, 1000, $resetTime);
        $this->assertEquals(RateLimitException::QUOTA_EXCEEDED, $exception->getErrorCode());
        
        $exception = RateLimitException::concurrentLimit(5, 3, 30);
        $this->assertEquals(RateLimitException::CONCURRENT_LIMIT, $exception->getErrorCode());
    }
    
    public function testRateLimitExceptionTimeCalculations(): void
    {
        $resetTime = time() + 1800; // 30 minutes from now
        $exception = RateLimitException::rateLimitExceeded(50, 100, $resetTime);
        
        $this->assertEquals(50, $exception->getRemainingRequests());
        $this->assertGreaterThan(1700, $exception->getTimeToReset()); // Should be close to 1800 seconds
        $this->assertLessThan(1800, $exception->getTimeToReset());
    }
    
    public function testExceptionInheritance(): void
    {
        $this->assertInstanceOf(DingTalkException::class, new ApiException());
        $this->assertInstanceOf(DingTalkException::class, new AuthException());
        $this->assertInstanceOf(DingTalkException::class, new ConfigException());
        $this->assertInstanceOf(DingTalkException::class, new ContainerException());
        $this->assertInstanceOf(DingTalkException::class, new NetworkException());
        $this->assertInstanceOf(DingTalkException::class, new ValidationException());
        $this->assertInstanceOf(DingTalkException::class, new RateLimitException());
    }
    
    public function testExceptionToArrayMethods(): void
    {
        $authException = AuthException::insufficientPermissions(['admin'], ['user']);
        $array = $authException->toArray();
        $this->assertArrayHasKey('auth_type', $array);
        $this->assertArrayHasKey('required_permissions', $array);
        $this->assertArrayHasKey('missing_permissions', $array);
        
        $validationException = ValidationException::missingParameter('name');
        $validationException->setValidationRules(['name' => 'required|string']);
        $array = $validationException->toArray();
        $this->assertArrayHasKey('failed_fields', $array);
        $this->assertArrayHasKey('validation_rules', $array);
        
        $rateLimitException = RateLimitException::rateLimitExceeded(100, 100, time() + 3600);
        $array = $rateLimitException->toArray();
        $this->assertArrayHasKey('limit_type', $array);
        $this->assertArrayHasKey('remaining_requests', $array);
        $this->assertArrayHasKey('can_retry', $array);
    }
}