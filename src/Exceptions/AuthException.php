<?php

declare(strict_types=1);

namespace DingTalk\Exceptions;

/**
 * 认证异常类
 * 
 * 用于处理身份认证、授权、token相关的异常
 */
class AuthException extends DingTalkException
{
    /**
     * 无效的访问令牌
     */
    public const INVALID_ACCESS_TOKEN = 'INVALID_ACCESS_TOKEN';
    
    /**
     * 访问令牌过期
     */
    public const ACCESS_TOKEN_EXPIRED = 'ACCESS_TOKEN_EXPIRED';
    
    /**
     * 无效的应用凭证
     */
    public const INVALID_APP_CREDENTIALS = 'INVALID_APP_CREDENTIALS';
    
    /**
     * 权限不足
     */
    public const INSUFFICIENT_PERMISSIONS = 'INSUFFICIENT_PERMISSIONS';
    
    /**
     * 签名验证失败
     */
    public const SIGNATURE_VERIFICATION_FAILED = 'SIGNATURE_VERIFICATION_FAILED';
    
    /**
     * 应用未授权
     */
    public const APP_NOT_AUTHORIZED = 'APP_NOT_AUTHORIZED';
    
    /**
     * 用户未授权
     */
    public const USER_NOT_AUTHORIZED = 'USER_NOT_AUTHORIZED';
    
    /**
     * 认证类型
     */
    private string $authType = '';
    
    /**
     * 所需权限
     */
    private array $requiredPermissions = [];
    
    /**
     * 当前权限
     */
    private array $currentPermissions = [];
    
    /**
     * 创建无效访问令牌异常
     */
    public static function invalidAccessToken(string $token = '', ?\Throwable $previous = null): self
    {
        $exception = new self(
            'Invalid access token provided',
            self::INVALID_ACCESS_TOKEN,
            ['token' => $token ? substr($token, 0, 10) . '...' : ''],
            401,
            $previous
        );
        
        $exception->setAuthType('access_token');
        
        return $exception;
    }
    
    /**
     * 创建访问令牌过期异常
     */
    public static function accessTokenExpired(int $expiredAt = 0, ?\Throwable $previous = null): self
    {
        $message = 'Access token has expired';
        if ($expiredAt > 0) {
            $message .= ' at ' . date('Y-m-d H:i:s', $expiredAt);
        }
        
        $exception = new self(
            $message,
            self::ACCESS_TOKEN_EXPIRED,
            ['expired_at' => $expiredAt],
            401,
            $previous
        );
        
        $exception->setAuthType('access_token');
        
        return $exception;
    }
    
    /**
     * 创建无效应用凭证异常
     */
    public static function invalidAppCredentials(string $appKey = '', ?\Throwable $previous = null): self
    {
        $exception = new self(
            'Invalid application credentials',
            self::INVALID_APP_CREDENTIALS,
            ['app_key' => $appKey],
            401,
            $previous
        );
        
        $exception->setAuthType('app_credentials');
        
        return $exception;
    }
    
    /**
     * 创建权限不足异常
     */
    public static function insufficientPermissions(
        array $requiredPermissions,
        array $currentPermissions = [],
        ?\Throwable $previous = null
    ): self {
        $missingPermissions = array_diff($requiredPermissions, $currentPermissions);
        
        $exception = new self(
            'Insufficient permissions. Missing: ' . implode(', ', $missingPermissions),
            self::INSUFFICIENT_PERMISSIONS,
            [
                'required_permissions' => $requiredPermissions,
                'current_permissions' => $currentPermissions,
                'missing_permissions' => $missingPermissions
            ],
            403,
            $previous
        );
        
        $exception->setAuthType('permissions');
        $exception->setRequiredPermissions($requiredPermissions);
        $exception->setCurrentPermissions($currentPermissions);
        
        return $exception;
    }
    
    /**
     * 创建签名验证失败异常
     */
    public static function signatureVerificationFailed(string $signature = '', ?\Throwable $previous = null): self
    {
        $exception = new self(
            'Signature verification failed',
            self::SIGNATURE_VERIFICATION_FAILED,
            ['signature' => $signature ? substr($signature, 0, 10) . '...' : ''],
            401,
            $previous
        );
        
        $exception->setAuthType('signature');
        
        return $exception;
    }
    
    /**
     * 创建应用未授权异常
     */
    public static function appNotAuthorized(string $appKey = '', string $scope = '', ?\Throwable $previous = null): self
    {
        $exception = new self(
            "Application '{$appKey}' is not authorized for scope '{$scope}'",
            self::APP_NOT_AUTHORIZED,
            [
                'app_key' => $appKey,
                'scope' => $scope
            ],
            403,
            $previous
        );
        
        $exception->setAuthType('app_authorization');
        
        return $exception;
    }
    
    /**
     * 创建用户未授权异常
     */
    public static function userNotAuthorized(string $userId = '', string $resource = '', ?\Throwable $previous = null): self
    {
        $exception = new self(
            "User '{$userId}' is not authorized to access resource '{$resource}'",
            self::USER_NOT_AUTHORIZED,
            [
                'user_id' => $userId,
                'resource' => $resource
            ],
            403,
            $previous
        );
        
        $exception->setAuthType('user_authorization');
        
        return $exception;
    }
    
    /**
     * 设置认证类型
     */
    public function setAuthType(string $authType): void
    {
        $this->authType = $authType;
    }
    
    /**
     * 获取认证类型
     */
    public function getAuthType(): string
    {
        return $this->authType;
    }
    
    /**
     * 设置所需权限
     */
    public function setRequiredPermissions(array $permissions): void
    {
        $this->requiredPermissions = $permissions;
    }
    
    /**
     * 获取所需权限
     */
    public function getRequiredPermissions(): array
    {
        return $this->requiredPermissions;
    }
    
    /**
     * 设置当前权限
     */
    public function setCurrentPermissions(array $permissions): void
    {
        $this->currentPermissions = $permissions;
    }
    
    /**
     * 获取当前权限
     */
    public function getCurrentPermissions(): array
    {
        return $this->currentPermissions;
    }
    
    /**
     * 获取缺失的权限
     */
    public function getMissingPermissions(): array
    {
        return array_diff($this->requiredPermissions, $this->currentPermissions);
    }
    
    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'auth_type' => $this->authType,
            'required_permissions' => $this->requiredPermissions,
            'current_permissions' => $this->currentPermissions,
            'missing_permissions' => $this->getMissingPermissions(),
        ]);
    }
}