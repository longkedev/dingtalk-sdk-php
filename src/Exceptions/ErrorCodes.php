<?php

declare(strict_types=1);

namespace DingTalk\Exceptions;

/**
 * 错误码常量定义
 * 
 * 集中定义所有API错误码常量，便于代码中引用和维护
 */
class ErrorCodes
{
    // ==================== 认证相关错误码 ====================
    
    /**
     * 新版API认证错误码
     */
    public const V2_AUTH_INVALID_ACCESS_TOKEN = '40001';
    public const V2_AUTH_ACCESS_TOKEN_EXPIRED = '40002';
    public const V2_AUTH_INVALID_APP_KEY = '40003';
    public const V2_AUTH_INVALID_APP_SECRET = '40004';
    
    /**
     * 旧版API认证错误码
     */
    public const V1_AUTH_INVALID_CREDENTIAL = '40001';
    public const V1_AUTH_INVALID_CORPID = '40013';
    public const V1_AUTH_INVALID_ACCESS_TOKEN = '40014';

    // ==================== API调用相关错误码 ====================
    
    /**
     * 新版API调用错误码
     */
    public const V2_API_INVALID_PARAMETER = '40015';
    public const V2_API_METHOD_NOT_ALLOWED = '40035';
    public const V2_API_INSUFFICIENT_PERMISSIONS = '40078';
    
    /**
     * 旧版API调用错误码
     */
    public const V1_API_USER_NOT_EXIST = '71006';
    public const V1_API_DEPARTMENT_NOT_EXIST = '60011';

    // ==================== 限流相关错误码 ====================
    
    /**
     * 新版API限流错误码
     */
    public const V2_RATE_LIMIT_EXCEEDED = '90018';
    public const V2_QUOTA_EXCEEDED = '90019';
    
    /**
     * 旧版API限流错误码
     */
    public const V1_RATE_LIMIT_EXCEEDED = '90018';

    // ==================== 网络相关错误码 ====================
    
    /**
     * 新版API网络错误码
     */
    public const V2_INTERNAL_SERVER_ERROR = '50001';
    public const V2_SERVICE_UNAVAILABLE = '50002';

    // ==================== 自定义SDK错误码 ====================
    
    /**
     * 配置相关错误码
     */
    public const SDK_CONFIG_FILE_NOT_FOUND = 'SDK_001';
    public const SDK_INVALID_CONFIG_FORMAT = 'SDK_002';
    
    /**
     * 网络相关错误码
     */
    public const SDK_CONNECTION_TIMEOUT = 'SDK_003';
    
    /**
     * 验证相关错误码
     */
    public const SDK_PARAMETER_VALIDATION_FAILED = 'SDK_004';
    
    /**
     * 容器相关错误码
     */
    public const SDK_SERVICE_NOT_FOUND = 'SDK_005';

    // ==================== 错误码分组 ====================
    
    /**
     * 认证相关错误码组
     */
    public const AUTH_ERROR_CODES = [
        self::V2_AUTH_INVALID_ACCESS_TOKEN,
        self::V2_AUTH_ACCESS_TOKEN_EXPIRED,
        self::V2_AUTH_INVALID_APP_KEY,
        self::V2_AUTH_INVALID_APP_SECRET,
        self::V1_AUTH_INVALID_CREDENTIAL,
        self::V1_AUTH_INVALID_CORPID,
        self::V1_AUTH_INVALID_ACCESS_TOKEN,
    ];

    /**
     * API调用相关错误码组
     */
    public const API_ERROR_CODES = [
        self::V2_API_INVALID_PARAMETER,
        self::V2_API_METHOD_NOT_ALLOWED,
        self::V2_API_INSUFFICIENT_PERMISSIONS,
        self::V1_API_USER_NOT_EXIST,
        self::V1_API_DEPARTMENT_NOT_EXIST,
    ];

    /**
     * 限流相关错误码组
     */
    public const RATE_LIMIT_ERROR_CODES = [
        self::V2_RATE_LIMIT_EXCEEDED,
        self::V2_QUOTA_EXCEEDED,
        self::V1_RATE_LIMIT_EXCEEDED,
    ];

    /**
     * 网络相关错误码组
     */
    public const NETWORK_ERROR_CODES = [
        self::V2_INTERNAL_SERVER_ERROR,
        self::V2_SERVICE_UNAVAILABLE,
        self::SDK_CONNECTION_TIMEOUT,
    ];

    /**
     * 配置相关错误码组
     */
    public const CONFIG_ERROR_CODES = [
        self::SDK_CONFIG_FILE_NOT_FOUND,
        self::SDK_INVALID_CONFIG_FORMAT,
    ];

    /**
     * 验证相关错误码组
     */
    public const VALIDATION_ERROR_CODES = [
        self::SDK_PARAMETER_VALIDATION_FAILED,
    ];

    /**
     * 容器相关错误码组
     */
    public const CONTAINER_ERROR_CODES = [
        self::SDK_SERVICE_NOT_FOUND,
    ];

    /**
     * 所有V2 API错误码
     */
    public const V2_ERROR_CODES = [
        self::V2_AUTH_INVALID_ACCESS_TOKEN,
        self::V2_AUTH_ACCESS_TOKEN_EXPIRED,
        self::V2_AUTH_INVALID_APP_KEY,
        self::V2_AUTH_INVALID_APP_SECRET,
        self::V2_API_INVALID_PARAMETER,
        self::V2_API_METHOD_NOT_ALLOWED,
        self::V2_API_INSUFFICIENT_PERMISSIONS,
        self::V2_RATE_LIMIT_EXCEEDED,
        self::V2_QUOTA_EXCEEDED,
        self::V2_INTERNAL_SERVER_ERROR,
        self::V2_SERVICE_UNAVAILABLE,
    ];

    /**
     * 所有V1 API错误码
     */
    public const V1_ERROR_CODES = [
        self::V1_AUTH_INVALID_CREDENTIAL,
        self::V1_AUTH_INVALID_CORPID,
        self::V1_AUTH_INVALID_ACCESS_TOKEN,
        self::V1_API_USER_NOT_EXIST,
        self::V1_API_DEPARTMENT_NOT_EXIST,
        self::V1_RATE_LIMIT_EXCEEDED,
    ];

    /**
     * 所有自定义SDK错误码
     */
    public const SDK_ERROR_CODES = [
        self::SDK_CONFIG_FILE_NOT_FOUND,
        self::SDK_INVALID_CONFIG_FORMAT,
        self::SDK_CONNECTION_TIMEOUT,
        self::SDK_PARAMETER_VALIDATION_FAILED,
        self::SDK_SERVICE_NOT_FOUND,
    ];

    // ==================== 工具方法 ====================

    /**
     * 检查是否为认证错误码
     */
    public static function isAuthError(string $errorCode): bool
    {
        return in_array($errorCode, self::AUTH_ERROR_CODES, true);
    }

    /**
     * 检查是否为API调用错误码
     */
    public static function isApiError(string $errorCode): bool
    {
        return in_array($errorCode, self::API_ERROR_CODES, true);
    }

    /**
     * 检查是否为限流错误码
     */
    public static function isRateLimitError(string $errorCode): bool
    {
        return in_array($errorCode, self::RATE_LIMIT_ERROR_CODES, true);
    }

    /**
     * 检查是否为网络错误码
     */
    public static function isNetworkError(string $errorCode): bool
    {
        return in_array($errorCode, self::NETWORK_ERROR_CODES, true);
    }

    /**
     * 检查是否为配置错误码
     */
    public static function isConfigError(string $errorCode): bool
    {
        return in_array($errorCode, self::CONFIG_ERROR_CODES, true);
    }

    /**
     * 检查是否为验证错误码
     */
    public static function isValidationError(string $errorCode): bool
    {
        return in_array($errorCode, self::VALIDATION_ERROR_CODES, true);
    }

    /**
     * 检查是否为容器错误码
     */
    public static function isContainerError(string $errorCode): bool
    {
        return in_array($errorCode, self::CONTAINER_ERROR_CODES, true);
    }

    /**
     * 检查是否为V2 API错误码
     */
    public static function isV2ErrorCode(string $errorCode): bool
    {
        return in_array($errorCode, self::V2_ERROR_CODES, true);
    }

    /**
     * 检查是否为V1 API错误码
     */
    public static function isV1ErrorCode(string $errorCode): bool
    {
        return in_array($errorCode, self::V1_ERROR_CODES, true);
    }

    /**
     * 检查是否为SDK自定义错误码
     */
    public static function isSdkErrorCode(string $errorCode): bool
    {
        return in_array($errorCode, self::SDK_ERROR_CODES, true);
    }

    /**
     * 获取错误码的API版本
     */
    public static function getApiVersion(string $errorCode): string
    {
        if (self::isV2ErrorCode($errorCode)) {
            return 'v2';
        }
        
        if (self::isV1ErrorCode($errorCode)) {
            return 'v1';
        }
        
        if (self::isSdkErrorCode($errorCode)) {
            return 'custom';
        }
        
        return 'unknown';
    }

    /**
     * 获取错误码的类型
     */
    public static function getErrorType(string $errorCode): string
    {
        if (self::isAuthError($errorCode)) {
            return 'auth';
        }
        
        if (self::isApiError($errorCode)) {
            return 'api';
        }
        
        if (self::isRateLimitError($errorCode)) {
            return 'rate_limit';
        }
        
        if (self::isNetworkError($errorCode)) {
            return 'network';
        }
        
        if (self::isConfigError($errorCode)) {
            return 'config';
        }
        
        if (self::isValidationError($errorCode)) {
            return 'validation';
        }
        
        if (self::isContainerError($errorCode)) {
            return 'container';
        }
        
        return 'unknown';
    }

    /**
     * 获取所有错误码
     */
    public static function getAllErrorCodes(): array
    {
        return array_merge(
            self::V2_ERROR_CODES,
            self::V1_ERROR_CODES,
            self::SDK_ERROR_CODES
        );
    }
}