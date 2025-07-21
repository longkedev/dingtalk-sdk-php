<?php

declare(strict_types=1);

namespace DingTalk\Exceptions;

/**
 * 网络异常类
 * 
 * 用于处理网络连接、超时、DNS解析等网络相关的异常
 */
class NetworkException extends DingTalkException
{
    /**
     * 连接超时
     */
    public const CONNECTION_TIMEOUT = 'CONNECTION_TIMEOUT';
    
    /**
     * 读取超时
     */
    public const READ_TIMEOUT = 'READ_TIMEOUT';
    
    /**
     * DNS解析失败
     */
    public const DNS_RESOLUTION_FAILED = 'DNS_RESOLUTION_FAILED';
    
    /**
     * 连接被拒绝
     */
    public const CONNECTION_REFUSED = 'CONNECTION_REFUSED';
    
    /**
     * SSL/TLS错误
     */
    public const SSL_ERROR = 'SSL_ERROR';
    
    /**
     * 网络不可达
     */
    public const NETWORK_UNREACHABLE = 'NETWORK_UNREACHABLE';
    
    /**
     * 创建连接超时异常
     */
    public static function connectionTimeout(int $timeout, ?\Throwable $previous = null): self
    {
        return new self(
            "Connection timeout after {$timeout} seconds",
            self::CONNECTION_TIMEOUT,
            ['timeout' => $timeout],
            0,
            $previous
        );
    }
    
    /**
     * 创建读取超时异常
     */
    public static function readTimeout(int $timeout, ?\Throwable $previous = null): self
    {
        return new self(
            "Read timeout after {$timeout} seconds",
            self::READ_TIMEOUT,
            ['timeout' => $timeout],
            0,
            $previous
        );
    }
    
    /**
     * 创建DNS解析失败异常
     */
    public static function dnsResolutionFailed(string $host, ?\Throwable $previous = null): self
    {
        return new self(
            "DNS resolution failed for host: {$host}",
            self::DNS_RESOLUTION_FAILED,
            ['host' => $host],
            0,
            $previous
        );
    }
    
    /**
     * 创建连接被拒绝异常
     */
    public static function connectionRefused(string $host, int $port, ?\Throwable $previous = null): self
    {
        return new self(
            "Connection refused to {$host}:{$port}",
            self::CONNECTION_REFUSED,
            ['host' => $host, 'port' => $port],
            0,
            $previous
        );
    }
    
    /**
     * 创建SSL错误异常
     */
    public static function sslError(string $message, ?\Throwable $previous = null): self
    {
        return new self(
            "SSL/TLS error: {$message}",
            self::SSL_ERROR,
            ['ssl_message' => $message],
            0,
            $previous
        );
    }
    
    /**
     * 创建网络不可达异常
     */
    public static function networkUnreachable(string $host, ?\Throwable $previous = null): self
    {
        return new self(
            "Network unreachable: {$host}",
            self::NETWORK_UNREACHABLE,
            ['host' => $host],
            0,
            $previous
        );
    }
}