<?php

declare(strict_types=1);

namespace DingTalk\Contracts;

/**
 * 认证管理接口
 * 
 * 定义认证授权的标准接口
 */
interface AuthInterface
{
    /**
     * 获取访问令牌
     * 
     * @param bool $refresh 是否强制刷新
     * @return string
     */
    public function getAccessToken(bool $refresh = false): string;

    /**
     * 刷新访问令牌
     * 
     * @return string
     */
    public function refreshAccessToken(): string;

    /**
     * 检查访问令牌是否有效
     * 
     * @param string|null $token 令牌
     * @return bool
     */
    public function isTokenValid(?string $token = null): bool;

    /**
     * 获取用户授权URL
     * 
     * @param string $redirectUri 回调地址
     * @param string $state 状态参数
     * @param array $scopes 权限范围
     * @return string
     */
    public function getAuthUrl(string $redirectUri, string $state = '', array $scopes = []): string;

    /**
     * 通过授权码获取用户信息
     * 
     * @param string $code 授权码
     * @return array
     */
    public function getUserByCode(string $code): array;

    /**
     * 获取JSAPI签名
     * 
     * @param string $url 当前页面URL
     * @param array $jsApiList JSAPI列表
     * @return array
     */
    public function getJsApiSignature(string $url, array $jsApiList = []): array;

    /**
     * 验证签名
     * 
     * @param array $data 数据
     * @param string $signature 签名
     * @return bool
     */
    public function verifySignature(array $data, string $signature): bool;

    /**
     * 生成签名
     * 
     * @param array $data 数据
     * @return string
     */
    public function generateSignature(array $data): string;

    /**
     * 设置应用凭证
     * 
     * @param string $appKey 应用Key
     * @param string $appSecret 应用Secret
     */
    public function setCredentials(string $appKey, string $appSecret): void;

    /**
     * 获取应用凭证
     * 
     * @return array
     */
    public function getCredentials(): array;
}