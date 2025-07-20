<?php

declare(strict_types=1);

namespace DingTalk\Exceptions;

/**
 * API异常类
 */
class ApiException extends DingTalkException
{
    /**
     * 请求ID
     */
    private string $requestId = '';

    /**
     * 响应数据
     */
    private array $responseData = [];

    /**
     * 设置请求ID
     */
    public function setRequestId(string $requestId): void
    {
        $this->requestId = $requestId;
    }

    /**
     * 获取请求ID
     */
    public function getRequestId(): string
    {
        return $this->requestId;
    }

    /**
     * 设置响应数据
     */
    public function setResponseData(array $data): void
    {
        $this->responseData = $data;
    }

    /**
     * 获取响应数据
     */
    public function getResponseData(): array
    {
        return $this->responseData;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'request_id' => $this->requestId,
            'response_data' => $this->responseData,
        ]);
    }
}