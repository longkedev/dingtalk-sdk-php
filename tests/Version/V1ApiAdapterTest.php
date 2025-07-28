<?php

declare(strict_types=1);

namespace DingTalk\Tests\Version;

use DingTalk\Version\V1ApiAdapter;
use DingTalk\Contracts\ConfigInterface;
use DingTalk\Contracts\HttpClientInterface;
use DingTalk\Contracts\AuthInterface;
use DingTalk\Exceptions\ApiException;
use DingTalk\Exceptions\AuthException;
use DingTalk\Exceptions\NetworkException;
use DingTalk\Exceptions\RateLimitException;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * V1 API适配器测试
 */
class V1ApiAdapterTest extends TestCase
{
    private V1ApiAdapter $adapter;
    /** @var MockObject&ConfigInterface */
    private MockObject $config;
    /** @var MockObject&HttpClientInterface */
    private MockObject $httpClient;
    /** @var MockObject&AuthInterface */
    private MockObject $auth;
    /** @var MockObject&LoggerInterface */
    private MockObject $logger;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigInterface::class);
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->auth = $this->createMock(AuthInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->adapter = new V1ApiAdapter(
            $this->config,
            $this->httpClient,
            $this->auth,
            $this->logger
        );
    }

    /**
     * 测试V1 API请求成功
     */
    public function testRequestSuccess(): void
    {
        // 模拟配置
        $this->config->method('get')->willReturnMap([
            ['v1_api_base_url', V1ApiAdapter::V1_BASE_URL, V1ApiAdapter::V1_BASE_URL],
            ['v1_api_timeout', 30, 30],
            ['sdk_version', '1.0.0', '1.0.0']
        ]);

        // 模拟认证
        $this->auth->method('getAccessToken')->willReturn('test_access_token');

        // 模拟HTTP响应
        $mockResponse = [
            'errcode' => 0,
            'errmsg' => 'ok',
            'userid' => 'test_user',
            'name' => 'Test User'
        ];

        $this->httpClient->method('post')->willReturn($mockResponse);

        // 执行请求
        $result = $this->adapter->request('user.get', ['userid' => 'test_user']);

        // 验证结果 - 响应数据已经过适配，V1格式转换为新格式
        $this->assertEquals(0, $result['errcode']);
        $this->assertEquals('ok', $result['errmsg']);
        $this->assertEquals('test_user', $result['user_id']); // userid -> user_id
        $this->assertEquals('Test User', $result['username']); // name -> username

        // 验证统计信息
        $stats = $this->adapter->getRequestStats();
        $this->assertEquals(1, $stats['total_requests']);
        $this->assertEquals(1, $stats['successful_requests']);
        $this->assertEquals(0, $stats['failed_requests']);
    }

    /**
     * 测试V1 API认证错误
     */
    public function testRequestAuthError(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('V1 Auth Error [40001]: Invalid access token');

        // 模拟配置
        $this->config->method('get')->willReturnMap([
            ['v1_api_base_url', V1ApiAdapter::V1_BASE_URL, V1ApiAdapter::V1_BASE_URL],
            ['v1_api_timeout', 30, 30],
            ['sdk_version', '1.0.0', '1.0.0']
        ]);

        // 模拟认证
        $this->auth->method('getAccessToken')->willReturn('invalid_token');

        // 模拟HTTP响应 - 认证错误
        $mockResponse = [
            'errcode' => 40001,
            'errmsg' => 'Invalid access token'
        ];

        $this->httpClient->method('post')->willReturn($mockResponse);

        // 执行请求
        $this->adapter->request('user.get', ['userid' => 'test_user']);
    }

    /**
     * 测试V1 API限流错误
     */
    public function testRequestRateLimitError(): void
    {
        $this->expectException(RateLimitException::class);
        $this->expectExceptionMessage('V1 Rate Limit Error [43001]: Request too frequent');

        // 模拟配置
        $this->config->method('get')->willReturnMap([
            ['v1_api_base_url', V1ApiAdapter::V1_BASE_URL, V1ApiAdapter::V1_BASE_URL],
            ['v1_api_timeout', 30, 30],
            ['sdk_version', '1.0.0', '1.0.0']
        ]);

        // 模拟认证
        $this->auth->method('getAccessToken')->willReturn('test_access_token');

        // 模拟HTTP响应 - 限流错误
        $mockResponse = [
            'errcode' => 43001,
            'errmsg' => 'Request too frequent'
        ];

        $this->httpClient->method('post')->willReturn($mockResponse);

        // 执行请求
        $this->adapter->request('user.get', ['userid' => 'test_user']);
    }

    /**
     * 测试V1 API一般错误
     */
    public function testRequestApiError(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('V1 API Error [50001]: Internal server error');

        // 模拟配置
        $this->config->method('get')->willReturnMap([
            ['v1_api_base_url', V1ApiAdapter::V1_BASE_URL, V1ApiAdapter::V1_BASE_URL],
            ['v1_api_timeout', 30, 30],
            ['sdk_version', '1.0.0', '1.0.0']
        ]);

        // 模拟认证
        $this->auth->method('getAccessToken')->willReturn('test_access_token');

        // 模拟HTTP响应 - 服务器错误
        $mockResponse = [
            'errcode' => 50001,
            'errmsg' => 'Internal server error'
        ];

        $this->httpClient->method('post')->willReturn($mockResponse);

        // 执行请求
        $this->adapter->request('user.get', ['userid' => 'test_user']);
    }

    /**
     * 测试请求参数适配
     */
    public function testAdaptRequestParams(): void
    {
        $originalParams = [
            'user_id' => 'test_user',
            'language' => 'zh_CN',
            'include_child' => true
        ];

        $adaptedParams = $this->adapter->adaptRequestParams('user.get', $originalParams);

        // 验证参数映射 - 从新格式映射到V1格式
        $this->assertArrayHasKey('userid', $adaptedParams);
        $this->assertArrayHasKey('lang', $adaptedParams);
        $this->assertEquals('test_user', $adaptedParams['userid']);
        $this->assertEquals('zh_CN', $adaptedParams['lang']);
        $this->assertEquals('true', $adaptedParams['include_child']);

        // 验证V1特有参数
        $this->assertArrayHasKey('timestamp', $adaptedParams);
        $this->assertArrayHasKey('nonce', $adaptedParams);
    }

    /**
     * 测试响应数据适配
     */
    public function testAdaptResponseData(): void
    {
        $originalResponse = [
            'errcode' => 0,
            'errmsg' => 'ok',
            'userid' => 'test_user',
            'name' => 'Test User',
            'department' => [1, 2]
        ];

        $adaptedResponse = $this->adapter->adaptResponseData('user.get', $originalResponse);

        // 验证响应映射 - 从V1格式映射到新格式
        $this->assertArrayHasKey('user_id', $adaptedResponse);
        $this->assertArrayHasKey('username', $adaptedResponse);
        $this->assertArrayHasKey('dept_list', $adaptedResponse);
        $this->assertEquals('test_user', $adaptedResponse['user_id']);
        $this->assertEquals('Test User', $adaptedResponse['username']);
        $this->assertEquals([1, 2], $adaptedResponse['dept_list']);
        
        // 验证基础字段保持不变
        $this->assertEquals(0, $adaptedResponse['errcode']);
        $this->assertEquals('ok', $adaptedResponse['errmsg']);
    }

    /**
     * 测试访问令牌认证
     */
    public function testAddAccessTokenAuth(): void
    {
        // 模拟认证
        $this->auth->method('getAccessToken')->willReturn('test_access_token');

        $params = ['userid' => 'test_user'];
        $authenticatedParams = $this->adapter->addAuthentication($params, 'user.get');

        // 验证访问令牌
        $this->assertArrayHasKey('access_token', $authenticatedParams);
        $this->assertEquals('test_access_token', $authenticatedParams['access_token']);
    }

    /**
     * 测试签名认证
     */
    public function testAddSignatureAuth(): void
    {
        // 模拟配置
        $this->config->method('get')->willReturnMap([
            ['app_key', null, 'test_app_key'],
            ['app_secret', null, 'test_app_secret']
        ]);

        $params = ['content' => 'test message'];
        $authenticatedParams = $this->adapter->addAuthentication($params, 'message.send');

        // 验证签名参数
        $this->assertArrayHasKey('app_key', $authenticatedParams);
        $this->assertArrayHasKey('timestamp', $authenticatedParams);
        $this->assertArrayHasKey('nonce', $authenticatedParams);
        $this->assertArrayHasKey('signature', $authenticatedParams);
        $this->assertEquals('test_app_key', $authenticatedParams['app_key']);
    }

    /**
     * 测试认证失败
     */
    public function testAuthenticationFailure(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Access token is required for V1 API');

        // 模拟认证失败
        $this->auth->method('getAccessToken')->willReturn('');

        $params = ['userid' => 'test_user'];
        $this->adapter->addAuthentication($params, 'user.get');
    }

    /**
     * 测试V1错误码检查
     */
    public function testCheckV1ErrorCode(): void
    {
        // 测试成功响应
        $successResponse = ['errcode' => 0, 'errmsg' => 'ok'];
        $this->adapter->checkV1ErrorCode($successResponse);
        $this->assertTrue(true); // 没有异常抛出

        // 测试认证错误
        $this->expectException(AuthException::class);
        $authErrorResponse = ['errcode' => 40001, 'errmsg' => 'Invalid access token'];
        $this->adapter->checkV1ErrorCode($authErrorResponse);
    }

    /**
     * 测试V1限流处理
     */
    public function testHandleV1RateLimit(): void
    {
        // 测试非限流响应
        $normalResponse = ['errcode' => 0, 'errmsg' => 'ok'];
        $shouldRetry = $this->adapter->handleV1RateLimit($normalResponse);
        $this->assertFalse($shouldRetry);

        // 测试限流响应
        $rateLimitResponse = ['errcode' => 43001, 'errmsg' => 'Request too frequent'];
        $shouldRetry = $this->adapter->handleV1RateLimit($rateLimitResponse);
        $this->assertTrue($shouldRetry);
    }

    /**
     * 测试网络错误
     */
    public function testNetworkError(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('V1 API request failed');

        // 模拟配置
        $this->config->method('get')->willReturnMap([
            ['v1_api_base_url', V1ApiAdapter::V1_BASE_URL, V1ApiAdapter::V1_BASE_URL],
            ['v1_api_timeout', 30, 30],
            ['sdk_version', '1.0.0', '1.0.0']
        ]);

        // 模拟认证
        $this->auth->method('getAccessToken')->willReturn('test_access_token');

        // 模拟网络错误
        $this->httpClient->method('post')->willThrowException(
            new \Exception('Network connection failed')
        );

        // 执行请求
        $this->adapter->request('user.get', ['userid' => 'test_user']);
    }

    /**
     * 测试GET请求
     */
    public function testGetRequest(): void
    {
        // 模拟配置
        $this->config->method('get')->willReturnMap([
            ['v1_api_base_url', V1ApiAdapter::V1_BASE_URL, V1ApiAdapter::V1_BASE_URL],
            ['v1_api_timeout', 30, 30],
            ['sdk_version', '1.0.0', '1.0.0']
        ]);

        // 模拟认证
        $this->auth->method('getAccessToken')->willReturn('test_access_token');

        // 模拟HTTP响应
        $mockResponse = [
            'errcode' => 0,
            'errmsg' => 'ok',
            'userid' => 'test_user'
        ];

        $this->httpClient->method('get')->willReturn($mockResponse);

        // 执行GET请求
        $result = $this->adapter->request('user.get', ['user_id' => 'test_user'], 'GET');

        // 验证结果
        $this->assertIsArray($result);
        $this->assertEquals(0, $result['errcode']);
    }

    /**
     * 测试请求统计
     */
    public function testRequestStats(): void
    {
        // 清除统计
        $this->adapter->clearRequestStats();
        $stats = $this->adapter->getRequestStats();
        $this->assertEquals(0, $stats['total_requests']);

        // 模拟配置和认证
        $this->config->method('get')->willReturnMap([
            ['v1_api_base_url', V1ApiAdapter::V1_BASE_URL, V1ApiAdapter::V1_BASE_URL],
            ['v1_api_timeout', 30, 30],
            ['sdk_version', '1.0.0', '1.0.0']
        ]);

        $this->auth->method('getAccessToken')->willReturn('test_access_token');

        // 模拟成功响应
        $this->httpClient->method('post')->willReturn([
            'errcode' => 0,
            'errmsg' => 'ok'
        ]);

        // 执行请求
        $this->adapter->request('user.get', ['userid' => 'test_user']);

        // 验证统计
        $stats = $this->adapter->getRequestStats();
        $this->assertEquals(1, $stats['total_requests']);
        $this->assertEquals(1, $stats['successful_requests']);
        $this->assertEquals(0, $stats['failed_requests']);
    }

    /**
     * 测试不同HTTP方法
     */
    public function testDifferentHttpMethods(): void
    {
        // 模拟配置
        $this->config->method('get')->willReturnMap([
            ['v1_api_base_url', V1ApiAdapter::V1_BASE_URL, V1ApiAdapter::V1_BASE_URL],
            ['v1_api_timeout', 30, 30],
            ['sdk_version', '1.0.0', '1.0.0']
        ]);

        // 模拟认证
        $this->auth->method('getAccessToken')->willReturn('test_access_token');

        // 模拟响应
        $mockResponse = ['errcode' => 0, 'errmsg' => 'ok'];

        // 测试PUT请求
        $this->httpClient->method('put')->willReturn($mockResponse);
        $result = $this->adapter->request('user.update', ['userid' => 'test'], 'PUT');
        $this->assertEquals(0, $result['errcode']);

        // 测试DELETE请求
        $this->httpClient->method('delete')->willReturn($mockResponse);
        $result = $this->adapter->request('user.delete', ['userid' => 'test'], 'DELETE');
        $this->assertEquals(0, $result['errcode']);
    }

    /**
     * 测试自定义请求选项
     */
    public function testCustomRequestOptions(): void
    {
        // 模拟配置
        $this->config->method('get')->willReturnMap([
            ['v1_api_base_url', V1ApiAdapter::V1_BASE_URL, V1ApiAdapter::V1_BASE_URL],
            ['v1_api_timeout', 30, 30],
            ['sdk_version', '1.0.0', '1.0.0']
        ]);

        // 模拟认证
        $this->auth->method('getAccessToken')->willReturn('test_access_token');

        // 模拟响应
        $mockResponse = ['errcode' => 0, 'errmsg' => 'ok'];
        $this->httpClient->method('post')->willReturn($mockResponse);

        // 使用自定义选项
        $customOptions = [
            'timeout' => 60,
            'headers' => [
                'Custom-Header' => 'Custom-Value'
            ]
        ];

        $result = $this->adapter->request('user.get', ['userid' => 'test'], 'POST', $customOptions);
        $this->assertEquals(0, $result['errcode']);
    }
}