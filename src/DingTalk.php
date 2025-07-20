<?php

declare(strict_types=1);

namespace DingTalk;

use DingTalk\Config\ConfigManager;
use DingTalk\Container\Container;
use DingTalk\Contracts\ConfigInterface;
use DingTalk\Contracts\ContainerInterface;
use DingTalk\Services\H5MicroAppService;
use DingTalk\Services\MiniProgramService;
use DingTalk\Services\UserService;
use DingTalk\Services\DepartmentService;
use DingTalk\Services\MessageService;
use DingTalk\Services\AttendanceService;
use DingTalk\Services\ApprovalService;
use DingTalk\Services\DocumentService;
use DingTalk\Services\ProjectService;
use DingTalk\Services\EnterpriseInternalService;
use DingTalk\Services\ThirdPartyService;

/**
 * 钉钉SDK主入口类
 * 
 * 提供统一的API访问接口，支持新旧版本自动适配
 * 
 * @author longkedev
 * @version 1.0.0
 */
class DingTalk
{
    /**
     * 服务容器
     */
    private ContainerInterface $container;

    /**
     * 配置管理器
     */
    private ConfigInterface $config;

    /**
     * 构造函数
     * 
     * @param array $config 配置数组
     */
    public function __construct(array $config = [])
    {
        $this->container = new Container();
        $this->config = new ConfigManager($config);
        
        $this->registerServices();
        $this->bootServices();
    }

    /**
     * 获取H5微应用服务
     */
    public function h5MicroApp(): H5MicroAppService
    {
        return $this->container->get(H5MicroAppService::class);
    }

    /**
     * 获取小程序服务
     */
    public function miniProgram(): MiniProgramService
    {
        return $this->container->get(MiniProgramService::class);
    }

    /**
     * 获取用户管理服务
     */
    public function user(): UserService
    {
        return $this->container->get(UserService::class);
    }

    /**
     * 获取部门管理服务
     */
    public function department(): DepartmentService
    {
        return $this->container->get(DepartmentService::class);
    }

    /**
     * 获取消息服务
     */
    public function message(): MessageService
    {
        return $this->container->get(MessageService::class);
    }

    /**
     * 获取考勤服务
     */
    public function attendance(): AttendanceService
    {
        return $this->container->get(AttendanceService::class);
    }

    /**
     * 获取审批服务
     */
    public function approval(): ApprovalService
    {
        return $this->container->get(ApprovalService::class);
    }

    /**
     * 获取文档服务
     */
    public function document(): DocumentService
    {
        return $this->container->get(DocumentService::class);
    }

    /**
     * 获取项目管理服务
     */
    public function project(): ProjectService
    {
        return $this->container->get(ProjectService::class);
    }

    /**
     * 获取企业内部应用服务
     */
    public function enterpriseInternal(): EnterpriseInternalService
    {
        return $this->container->get(EnterpriseInternalService::class);
    }

    /**
     * 获取第三方应用服务
     */
    public function thirdParty(): ThirdPartyService
    {
        return $this->container->get(ThirdPartyService::class);
    }

    /**
     * 获取配置
     */
    public function getConfig(): ConfigInterface
    {
        return $this->config;
    }

    /**
     * 获取容器
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * 注册服务到容器
     */
    private function registerServices(): void
    {
        // 注册配置
        $this->container->singleton(ConfigInterface::class, function () {
            return $this->config;
        });

        // 注册容器自身
        $this->container->singleton(ContainerInterface::class, function () {
            return $this->container;
        });

        // 注册核心服务
        $this->container->singleton(H5MicroAppService::class);
        $this->container->singleton(MiniProgramService::class);
        $this->container->singleton(UserService::class);
        $this->container->singleton(DepartmentService::class);
        $this->container->singleton(MessageService::class);
        $this->container->singleton(AttendanceService::class);
        $this->container->singleton(ApprovalService::class);
        $this->container->singleton(DocumentService::class);
        $this->container->singleton(ProjectService::class);
        $this->container->singleton(EnterpriseInternalService::class);
        $this->container->singleton(ThirdPartyService::class);
    }

    /**
     * 启动服务
     */
    private function bootServices(): void
    {
        // 这里可以添加服务启动逻辑
        // 例如：初始化缓存、日志、监控等
    }
}