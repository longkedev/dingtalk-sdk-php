<?php

declare(strict_types=1);

namespace DingTalk\Exceptions;

/**
 * 验证异常类
 * 
 * 用于处理参数验证、数据格式验证等验证相关的异常
 */
class ValidationException extends DingTalkException
{
    /**
     * 参数缺失
     */
    public const MISSING_PARAMETER = 'MISSING_PARAMETER';
    
    /**
     * 参数格式错误
     */
    public const INVALID_FORMAT = 'INVALID_FORMAT';
    
    /**
     * 参数值无效
     */
    public const INVALID_VALUE = 'INVALID_VALUE';
    
    /**
     * 参数长度错误
     */
    public const INVALID_LENGTH = 'INVALID_LENGTH';
    
    /**
     * 参数类型错误
     */
    public const INVALID_TYPE = 'INVALID_TYPE';
    
    /**
     * 验证失败的字段
     */
    private array $failedFields = [];
    
    /**
     * 验证规则
     */
    private array $validationRules = [];
    
    /**
     * 创建参数缺失异常
     */
    public static function missingParameter(string $parameter, ?\Throwable $previous = null): self
    {
        $exception = new self(
            "Missing required parameter: {$parameter}",
            self::MISSING_PARAMETER,
            ['parameter' => $parameter],
            0,
            $previous
        );
        
        $exception->addFailedField($parameter, 'required');
        
        return $exception;
    }
    
    /**
     * 创建格式错误异常
     */
    public static function invalidFormat(string $field, string $expectedFormat, string $actualValue = '', ?\Throwable $previous = null): self
    {
        $exception = new self(
            "Invalid format for field '{$field}'. Expected: {$expectedFormat}",
            self::INVALID_FORMAT,
            [
                'field' => $field,
                'expected_format' => $expectedFormat,
                'actual_value' => $actualValue
            ],
            0,
            $previous
        );
        
        $exception->addFailedField($field, "format:{$expectedFormat}");
        
        return $exception;
    }
    
    /**
     * 创建值无效异常
     */
    public static function invalidValue(string $field, $value, array $allowedValues = [], ?\Throwable $previous = null): self
    {
        $message = "Invalid value for field '{$field}': " . json_encode($value);
        if (!empty($allowedValues)) {
            $message .= ". Allowed values: " . implode(', ', $allowedValues);
        }
        
        $exception = new self(
            $message,
            self::INVALID_VALUE,
            [
                'field' => $field,
                'value' => $value,
                'allowed_values' => $allowedValues
            ],
            0,
            $previous
        );
        
        $exception->addFailedField($field, 'value');
        
        return $exception;
    }
    
    /**
     * 创建长度错误异常
     */
    public static function invalidLength(string $field, int $actualLength, ?int $minLength = null, ?int $maxLength = null, ?\Throwable $previous = null): self
    {
        $message = "Invalid length for field '{$field}': {$actualLength}";
        
        if ($minLength !== null && $maxLength !== null) {
            $message .= ". Expected length between {$minLength} and {$maxLength}";
        } elseif ($minLength !== null) {
            $message .= ". Minimum length: {$minLength}";
        } elseif ($maxLength !== null) {
            $message .= ". Maximum length: {$maxLength}";
        }
        
        $exception = new self(
            $message,
            self::INVALID_LENGTH,
            [
                'field' => $field,
                'actual_length' => $actualLength,
                'min_length' => $minLength,
                'max_length' => $maxLength
            ],
            0,
            $previous
        );
        
        $exception->addFailedField($field, 'length');
        
        return $exception;
    }
    
    /**
     * 创建类型错误异常
     */
    public static function invalidType(string $field, string $expectedType, string $actualType, ?\Throwable $previous = null): self
    {
        $exception = new self(
            "Invalid type for field '{$field}'. Expected: {$expectedType}, got: {$actualType}",
            self::INVALID_TYPE,
            [
                'field' => $field,
                'expected_type' => $expectedType,
                'actual_type' => $actualType
            ],
            0,
            $previous
        );
        
        $exception->addFailedField($field, "type:{$expectedType}");
        
        return $exception;
    }
    
    /**
     * 添加验证失败的字段
     */
    public function addFailedField(string $field, string $rule): void
    {
        $this->failedFields[$field] = $rule;
    }
    
    /**
     * 获取验证失败的字段
     */
    public function getFailedFields(): array
    {
        return $this->failedFields;
    }
    
    /**
     * 设置验证规则
     */
    public function setValidationRules(array $rules): void
    {
        $this->validationRules = $rules;
    }
    
    /**
     * 获取验证规则
     */
    public function getValidationRules(): array
    {
        return $this->validationRules;
    }
    
    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'failed_fields' => $this->failedFields,
            'validation_rules' => $this->validationRules,
        ]);
    }
}