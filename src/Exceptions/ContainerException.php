<?php

declare(strict_types=1);

namespace DingTalk\Exceptions;

/**
 * 容器异常类
 * 
 * 用于处理依赖注入容器、服务绑定、服务解析等容器相关的异常
 */
class ContainerException extends DingTalkException
{
    /**
     * 服务未找到
     */
    public const SERVICE_NOT_FOUND = 'SERVICE_NOT_FOUND';
    
    /**
     * 服务绑定失败
     */
    public const SERVICE_BINDING_FAILED = 'SERVICE_BINDING_FAILED';
    
    /**
     * 循环依赖
     */
    public const CIRCULAR_DEPENDENCY = 'CIRCULAR_DEPENDENCY';
    
    /**
     * 服务实例化失败
     */
    public const SERVICE_INSTANTIATION_FAILED = 'SERVICE_INSTANTIATION_FAILED';
    
    /**
     * 无效的服务定义
     */
    public const INVALID_SERVICE_DEFINITION = 'INVALID_SERVICE_DEFINITION';
    
    /**
     * 容器锁定
     */
    public const CONTAINER_LOCKED = 'CONTAINER_LOCKED';
    
    /**
     * 服务标识符
     */
    private string $serviceId = '';
    
    /**
     * 服务类型
     */
    private string $serviceType = '';
    
    /**
     * 依赖链
     */
    private array $dependencyChain = [];
    
    /**
     * 创建服务未找到异常
     */
    public static function serviceNotFound(string $serviceId, ?\Throwable $previous = null): self
    {
        $exception = new self(
            "Service not found in container: {$serviceId}",
            self::SERVICE_NOT_FOUND,
            ['service_id' => $serviceId],
            0,
            $previous
        );
        
        $exception->setServiceId($serviceId);
        
        return $exception;
    }
    
    /**
     * 创建服务绑定失败异常
     */
    public static function serviceBindingFailed(string $serviceId, string $reason = '', ?\Throwable $previous = null): self
    {
        $message = "Failed to bind service: {$serviceId}";
        if ($reason) {
            $message .= ". Reason: {$reason}";
        }
        
        $exception = new self(
            $message,
            self::SERVICE_BINDING_FAILED,
            [
                'service_id' => $serviceId,
                'reason' => $reason
            ],
            0,
            $previous
        );
        
        $exception->setServiceId($serviceId);
        
        return $exception;
    }
    
    /**
     * 创建循环依赖异常
     */
    public static function circularDependency(array $dependencyChain, ?\Throwable $previous = null): self
    {
        $chainStr = implode(' -> ', $dependencyChain);
        
        $exception = new self(
            "Circular dependency detected: {$chainStr}",
            self::CIRCULAR_DEPENDENCY,
            ['dependency_chain' => $dependencyChain],
            0,
            $previous
        );
        
        $exception->setDependencyChain($dependencyChain);
        
        return $exception;
    }
    
    /**
     * 创建服务实例化失败异常
     */
    public static function serviceInstantiationFailed(
        string $serviceId,
        string $serviceType = '',
        string $reason = '',
        ?\Throwable $previous = null
    ): self {
        $message = "Failed to instantiate service: {$serviceId}";
        if ($serviceType) {
            $message .= " (type: {$serviceType})";
        }
        if ($reason) {
            $message .= ". Reason: {$reason}";
        }
        
        $exception = new self(
            $message,
            self::SERVICE_INSTANTIATION_FAILED,
            [
                'service_id' => $serviceId,
                'service_type' => $serviceType,
                'reason' => $reason
            ],
            0,
            $previous
        );
        
        $exception->setServiceId($serviceId);
        $exception->setServiceType($serviceType);
        
        return $exception;
    }
    
    /**
     * 创建无效服务定义异常
     */
    public static function invalidServiceDefinition(
        string $serviceId,
        string $reason = '',
        ?\Throwable $previous = null
    ): self {
        $message = "Invalid service definition: {$serviceId}";
        if ($reason) {
            $message .= ". Reason: {$reason}";
        }
        
        $exception = new self(
            $message,
            self::INVALID_SERVICE_DEFINITION,
            [
                'service_id' => $serviceId,
                'reason' => $reason
            ],
            0,
            $previous
        );
        
        $exception->setServiceId($serviceId);
        
        return $exception;
    }
    
    /**
     * 创建容器锁定异常
     */
    public static function containerLocked(string $operation = '', ?\Throwable $previous = null): self
    {
        $message = 'Container is locked and cannot be modified';
        if ($operation) {
            $message .= ". Attempted operation: {$operation}";
        }
        
        $exception = new self(
            $message,
            self::CONTAINER_LOCKED,
            ['operation' => $operation],
            0,
            $previous
        );
        
        return $exception;
    }
    
    /**
     * 设置服务标识符
     */
    public function setServiceId(string $serviceId): void
    {
        $this->serviceId = $serviceId;
    }
    
    /**
     * 获取服务标识符
     */
    public function getServiceId(): string
    {
        return $this->serviceId;
    }
    
    /**
     * 设置服务类型
     */
    public function setServiceType(string $serviceType): void
    {
        $this->serviceType = $serviceType;
    }
    
    /**
     * 获取服务类型
     */
    public function getServiceType(): string
    {
        return $this->serviceType;
    }
    
    /**
     * 设置依赖链
     */
    public function setDependencyChain(array $chain): void
    {
        $this->dependencyChain = $chain;
    }
    
    /**
     * 获取依赖链
     */
    public function getDependencyChain(): array
    {
        return $this->dependencyChain;
    }
    
    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'service_id' => $this->serviceId,
            'service_type' => $this->serviceType,
            'dependency_chain' => $this->dependencyChain,
        ]);
    }
}