<?php

declare(strict_types=1);

namespace DingTalk\Exceptions;

use Exception;

/**
 * 钉钉SDK基础异常类
 */
class DingTalkException extends Exception
{
    /**
     * 错误代码
     */
    protected string $errorCode = '';

    /**
     * 错误详情
     */
    protected array $errorDetails = [];

    /**
     * 构造函数
     * 
     * @param string $message 错误消息
     * @param string $errorCode 错误代码
     * @param array $errorDetails 错误详情
     * @param int $code HTTP状态码
     * @param Exception|null $previous 上一个异常
     */
    public function __construct(
        string $message = '',
        string $errorCode = '',
        array $errorDetails = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->errorCode = $errorCode;
        $this->errorDetails = $errorDetails;
    }

    /**
     * 获取错误代码
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * 获取错误详情
     */
    public function getErrorDetails(): array
    {
        return $this->errorDetails;
    }

    /**
     * 设置错误详情
     */
    public function setErrorDetails(array $details): void
    {
        $this->errorDetails = $details;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
            'error_details' => $this->errorDetails,
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTraceAsString(),
        ];
    }

    /**
     * 转换为JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }
}