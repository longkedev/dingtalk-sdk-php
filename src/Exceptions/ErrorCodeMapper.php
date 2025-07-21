<?php

declare(strict_types=1);

namespace DingTalk\Exceptions;

/**
 * 错误码映射器
 * 
 * 实现新旧API错误码统一映射，提供错误消息国际化、
 * 错误上下文信息收集和错误恢复建议功能
 */
class ErrorCodeMapper
{
    /**
     * V2 API错误码映射
     */
    private const V2_ERROR_CODES = [
        // 认证错误
        '40001' => ['type' => 'auth', 'message_key' => 'auth.invalid_access_token', 'recovery_key' => 'auth.refresh_access_token'],
        '40002' => ['type' => 'auth', 'message_key' => 'auth.access_token_expired', 'recovery_key' => 'auth.refresh_access_token'],
        '40003' => ['type' => 'auth', 'message_key' => 'auth.invalid_app_key', 'recovery_key' => 'auth.check_app_credentials'],
        '40004' => ['type' => 'auth', 'message_key' => 'auth.invalid_app_secret', 'recovery_key' => 'auth.check_app_credentials'],
        
        // API调用错误
        '40015' => ['type' => 'api', 'message_key' => 'api.invalid_parameter', 'recovery_key' => 'api.check_parameters'],
        '40035' => ['type' => 'api', 'message_key' => 'api.method_not_allowed', 'recovery_key' => 'api.check_method'],
        '40078' => ['type' => 'api', 'message_key' => 'api.insufficient_permissions', 'recovery_key' => 'api.check_permissions'],
        
        // 限流错误
        '90018' => ['type' => 'rate_limit', 'message_key' => 'rate_limit.exceeded', 'recovery_key' => 'rate_limit.wait_and_retry'],
        '90019' => ['type' => 'rate_limit', 'message_key' => 'rate_limit.quota_exceeded', 'recovery_key' => 'rate_limit.upgrade_quota'],
        
        // 网络错误
        '50001' => ['type' => 'network', 'message_key' => 'network.internal_server_error', 'recovery_key' => 'network.retry_later'],
        '50002' => ['type' => 'network', 'message_key' => 'network.service_unavailable', 'recovery_key' => 'network.retry_later'],
    ];

    /**
     * 旧版API错误码映射
     */
    private const V1_ERROR_CODES = [
        // 认证相关错误
        '40001' => [
            'type' => 'auth',
            'message' => 'Invalid credential',
            'message_zh' => '无效的凭证',
            'recovery' => 'Please check your credentials',
            'recovery_zh' => '请检查您的凭证'
        ],
        '40013' => [
            'type' => 'auth',
            'message' => 'Invalid corpid',
            'message_zh' => '无效的企业ID',
            'recovery' => 'Please check your corp ID',
            'recovery_zh' => '请检查您的企业ID'
        ],
        '40014' => [
            'type' => 'auth',
            'message' => 'Invalid access_token',
            'message_zh' => '无效的访问令牌',
            'recovery' => 'Please refresh your access token',
            'recovery_zh' => '请刷新您的访问令牌'
        ],
        
        // API调用相关错误
        '71006' => [
            'type' => 'api',
            'message' => 'User not exist',
            'message_zh' => '用户不存在',
            'recovery' => 'Please check the user ID',
            'recovery_zh' => '请检查用户ID'
        ],
        '60011' => [
            'type' => 'api',
            'message' => 'Department not exist',
            'message_zh' => '部门不存在',
            'recovery' => 'Please check the department ID',
            'recovery_zh' => '请检查部门ID'
        ],
        
        // 限流相关错误
        '90018' => [
            'type' => 'rate_limit',
            'message' => 'Interface call frequency limit',
            'message_zh' => '接口调用频率限制',
            'recovery' => 'Please reduce the request frequency',
            'recovery_zh' => '请降低请求频率'
        ]
    ];

    /**
     * 自定义错误码映射
     */
    private const CUSTOM_ERROR_CODES = [
        // SDK内部错误
        'SDK_001' => [
            'type' => 'config',
            'message' => 'Configuration file not found',
            'message_zh' => '配置文件未找到',
            'recovery' => 'Please create the configuration file',
            'recovery_zh' => '请创建配置文件'
        ],
        'SDK_002' => [
            'type' => 'config',
            'message' => 'Invalid configuration format',
            'message_zh' => '无效的配置格式',
            'recovery' => 'Please check the configuration file format',
            'recovery_zh' => '请检查配置文件格式'
        ],
        'SDK_003' => [
            'type' => 'network',
            'message' => 'Connection timeout',
            'message_zh' => '连接超时',
            'recovery' => 'Please check your network connection',
            'recovery_zh' => '请检查您的网络连接'
        ],
        'SDK_004' => [
            'type' => 'validation',
            'message' => 'Parameter validation failed',
            'message_zh' => '参数验证失败',
            'recovery' => 'Please check the parameter format',
            'recovery_zh' => '请检查参数格式'
        ],
        'SDK_005' => [
            'type' => 'container',
            'message' => 'Service not found in container',
            'message_zh' => '容器中未找到服务',
            'recovery' => 'Please register the service in container',
            'recovery_zh' => '请在容器中注册服务'
        ]
    ];

    /**
     * 错误码到异常类型的映射
     */
    private const ERROR_TYPE_TO_EXCEPTION = [
        'auth' => AuthException::class,
        'api' => ApiException::class,
        'network' => NetworkException::class,
        'rate_limit' => RateLimitException::class,
        'validation' => ValidationException::class,
        'config' => ConfigException::class,
        'container' => ContainerException::class
    ];

    /**
     * 当前语言
     */
    private string $language = 'en';

    /**
     * 错误消息翻译器
     */
    private ErrorMessageTranslator $translator;

    /**
     * 错误上下文收集器
     */
    private ErrorContextCollector $contextCollector;

    /**
     * 构造函数
     */
    public function __construct(string $language = 'en', bool $collectSensitiveInfo = false)
    {
        $this->language = $language;
        $this->translator = new ErrorMessageTranslator($language);
        $this->contextCollector = new ErrorContextCollector($collectSensitiveInfo);
    }

    /**
     * 设置语言
     */
    public function setLanguage(string $language): self
    {
        $this->language = $language;
        $this->translator->setLanguage($language);
        return $this;
    }

    /**
     * 获取当前语言
     */
    public function getLanguage(): string
    {
        return $this->language;
    }

    /**
     * 映射错误码到错误信息
     */
    public function mapErrorCode(string $errorCode, string $apiVersion = 'v2'): array
    {
        $errorMap = $this->getErrorMap($apiVersion);
        
        if (!isset($errorMap[$errorCode])) {
            return $this->getUnknownErrorInfo($errorCode);
        }

        $errorInfo = $errorMap[$errorCode];
        
        return [
            'code' => $errorCode,
            'type' => $errorInfo['type'],
            'message' => $this->getLocalizedMessage($errorInfo),
            'recovery' => $this->getLocalizedRecovery($errorInfo),
            'api_version' => $apiVersion,
            'exception_class' => $this->getExceptionClass($errorInfo['type'])
        ];
    }

    /**
     * 根据错误码创建异常实例
     */
    public function createException(string $errorCode, string $apiVersion = 'v2', ?\Throwable $previous = null): DingTalkException
    {
        $errorInfo = $this->mapErrorCode($errorCode, $apiVersion);
        $exceptionClass = $errorInfo['exception_class'];
        
        $context = [
            'error_code' => $errorCode,
            'api_version' => $apiVersion,
            'error_type' => $errorInfo['type'],
            'recovery_suggestion' => $errorInfo['recovery']
        ];

        return new $exceptionClass(
            $errorInfo['message'],
            $errorCode,
            $context,
            0,
            $previous
        );
    }

    /**
     * 收集错误上下文信息
     */
    public function collectErrorContext(string $errorCode, array $additionalContext = []): array
    {
        // 添加错误信息到上下文收集器
        $this->contextCollector->addErrorContext([
            'code' => $errorCode,
            'type' => ErrorCodes::getErrorType($errorCode),
            'api_version' => ErrorCodes::getApiVersion($errorCode)
        ]);

        // 添加自定义上下文
        foreach ($additionalContext as $key => $value) {
            $this->contextCollector->addCustomContext($key, $value);
        }

        return $this->contextCollector->getContext();
    }

    /**
     * 添加HTTP请求上下文
     */
    public function addHttpContext(array $requestInfo): self
    {
        $this->contextCollector->addHttpContext($requestInfo);
        return $this;
    }

    /**
     * 添加API调用上下文
     */
    public function addApiContext(array $apiInfo): self
    {
        $this->contextCollector->addApiContext($apiInfo);
        return $this;
    }

    /**
     * 添加认证上下文
     */
    public function addAuthContext(array $authInfo): self
    {
        $this->contextCollector->addAuthContext($authInfo);
        return $this;
    }

    /**
     * 添加用户上下文
     */
    public function addUserContext(array $userInfo): self
    {
        $this->contextCollector->addUserContext($userInfo);
        return $this;
    }

    /**
     * 判断是否为认证错误
     */
    public function isAuthError(string $errorCode, string $apiVersion = 'v2'): bool
    {
        $errorInfo = $this->mapErrorCode($errorCode, $apiVersion);
        return $errorInfo['type'] === 'auth';
    }

    /**
     * 判断是否为API错误
     */
    public function isApiError(string $errorCode, string $apiVersion = 'v2'): bool
    {
        $errorInfo = $this->mapErrorCode($errorCode, $apiVersion);
        return $errorInfo['type'] === 'api';
    }

    /**
     * 判断是否为限流错误
     */
    public function isRateLimitError(string $errorCode, string $apiVersion = 'v2'): bool
    {
        $errorInfo = $this->mapErrorCode($errorCode, $apiVersion);
        return $errorInfo['type'] === 'rate_limit';
    }

    /**
     * 判断是否为网络错误
     */
    public function isNetworkError(string $errorCode, string $apiVersion = 'v2'): bool
    {
        $errorInfo = $this->mapErrorCode($errorCode, $apiVersion);
        return $errorInfo['type'] === 'network';
    }

    /**
     * 判断是否为配置错误
     */
    public function isConfigError(string $errorCode, string $apiVersion = 'v2'): bool
    {
        $errorInfo = $this->mapErrorCode($errorCode, $apiVersion);
        return $errorInfo['type'] === 'config';
    }

    /**
     * 判断是否为验证错误
     */
    public function isValidationError(string $errorCode, string $apiVersion = 'v2'): bool
    {
        $errorInfo = $this->mapErrorCode($errorCode, $apiVersion);
        return $errorInfo['type'] === 'validation';
    }

    /**
     * 判断是否为容器错误
     */
    public function isContainerError(string $errorCode, string $apiVersion = 'v2'): bool
    {
        $errorInfo = $this->mapErrorCode($errorCode, $apiVersion);
        return $errorInfo['type'] === 'container';
    }

    /**
     * 获取内部的上下文收集器实例
     */
    public function getContextCollector(): ErrorContextCollector
    {
        return $this->contextCollector;
    }

    /**
     * 获取内部的消息翻译器实例
     */
    public function getTranslator(): ErrorMessageTranslator
    {
        return $this->translator;
    }

    /**
     * 获取错误恢复建议
     */
    public function getRecoverySuggestion(string $errorCode, string $apiVersion = 'v2'): string
    {
        $errorInfo = $this->mapErrorCode($errorCode, $apiVersion);
        return $errorInfo['recovery'];
    }

    /**
     * 获取所有支持的错误码
     */
    public function getSupportedErrorCodes(string $apiVersion = 'all'): array
    {
        if ($apiVersion === 'v1') {
            return array_keys(self::V1_ERROR_CODES);
        }
        
        if ($apiVersion === 'v2') {
            return array_keys(self::V2_ERROR_CODES);
        }
        
        if ($apiVersion === 'custom') {
            return array_keys(self::CUSTOM_ERROR_CODES);
        }
        
        return array_merge(
            array_keys(self::V1_ERROR_CODES),
            array_keys(self::V2_ERROR_CODES),
            array_keys(self::CUSTOM_ERROR_CODES)
        );
    }

    /**
     * 获取错误映射表
     */
    private function getErrorMap(string $apiVersion): array
    {
        switch ($apiVersion) {
            case 'v1':
                return self::V1_ERROR_CODES;
            case 'v2':
                return self::V2_ERROR_CODES;
            case 'custom':
                return self::CUSTOM_ERROR_CODES;
            default:
                return array_merge(self::V2_ERROR_CODES, self::CUSTOM_ERROR_CODES);
        }
    }

    /**
     * 获取本地化消息
     */
    private function getLocalizedMessage(array $errorInfo): string
    {
        if (isset($errorInfo['message_key'])) {
            return $this->translator->translateMessage($errorInfo['message_key']);
        }
        
        // 兼容旧格式
        $messageKey = $this->language === 'zh' ? 'message_zh' : 'message';
        return $errorInfo[$messageKey] ?? $errorInfo['message'] ?? '未知错误';
    }

    /**
     * 获取本地化恢复建议
     */
    private function getLocalizedRecovery(array $errorInfo): string
    {
        if (isset($errorInfo['recovery_key'])) {
            return $this->translator->translateRecovery($errorInfo['recovery_key']);
        }
        
        // 兼容旧格式
        $recoveryKey = $this->language === 'zh' ? 'recovery_zh' : 'recovery';
        return $errorInfo[$recoveryKey] ?? $errorInfo['recovery'] ?? '请联系技术支持';
    }

    /**
     * 获取异常类
     */
    private function getExceptionClass(string $errorType): string
    {
        return self::ERROR_TYPE_TO_EXCEPTION[$errorType] ?? DingTalkException::class;
    }

    /**
     * 获取未知错误信息
     */
    private function getUnknownErrorInfo(string $errorCode): array
    {
        $message = $this->language === 'zh' ? '未知错误' : 'Unknown error';
        $recovery = $this->language === 'zh' ? '请联系技术支持' : 'Please contact technical support';
        
        return [
            'code' => $errorCode,
            'type' => 'unknown',
            'message' => $message,
            'recovery' => $recovery,
            'api_version' => 'unknown',
            'exception_class' => DingTalkException::class
        ];
    }

    /**
     * 获取SDK版本
     */
    private function getSdkVersion(): string
    {
        // 这里可以从composer.json或其他地方读取版本信息
        return '1.0.0';
    }
}