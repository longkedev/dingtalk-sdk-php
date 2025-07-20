<?php

declare(strict_types=1);

namespace DingTalk\Container;

use DingTalk\Contracts\ContainerInterface;
use DingTalk\Exceptions\ContainerException;
use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;

/**
 * 服务容器
 * 
 * 实现依赖注入容器功能
 */
class Container implements ContainerInterface
{
    /**
     * 绑定的服务
     */
    private array $bindings = [];

    /**
     * 共享的实例
     */
    private array $instances = [];

    /**
     * 别名映射
     */
    private array $aliases = [];

    /**
     * 扩展回调
     */
    private array $extenders = [];

    /**
     * 标签映射
     */
    private array $tags = [];

    /**
     * 构建栈
     */
    private array $buildStack = [];

    /**
     * 服务提供者
     */
    private array $serviceProviders = [];

    /**
     * 延迟服务
     */
    private array $deferredServices = [];

    /**
     * 已启动的提供者
     */
    private array $loadedProviders = [];

    /**
     * {@inheritdoc}
     */
    public function bind(string $abstract, $concrete = null, bool $shared = false): void
    {
        $this->dropStaleInstances($abstract);

        if ($concrete === null) {
            $concrete = $abstract;
        }

        if (!$concrete instanceof Closure) {
            $concrete = $this->getClosure($abstract, $concrete);
        }

        $this->bindings[$abstract] = compact('concrete', 'shared');
    }

    /**
     * {@inheritdoc}
     */
    public function singleton(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * {@inheritdoc}
     */
    public function instance(string $abstract, $instance): void
    {
        $this->removeAbstractAlias($abstract);
        
        unset($this->aliases[$abstract]);
        
        $this->instances[$abstract] = $instance;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $abstract, array $parameters = [])
    {
        return $this->resolve($abstract, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function make(string $abstract, array $parameters = [])
    {
        return $this->resolve($abstract, $parameters, false);
    }

    /**
     * {@inheritdoc}
     */
    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) ||
               isset($this->instances[$abstract]) ||
               $this->isAlias($abstract);
    }

    /**
     * {@inheritdoc}
     */
    public function isShared(string $abstract): bool
    {
        return isset($this->instances[$abstract]) ||
               (isset($this->bindings[$abstract]['shared']) &&
                $this->bindings[$abstract]['shared'] === true);
    }

    /**
     * {@inheritdoc}
     */
    public function call($callback, array $parameters = [])
    {
        return BoundMethod::call($this, $callback, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function extend(string $abstract, Closure $closure): void
    {
        $abstract = $this->getAlias($abstract);

        if (isset($this->instances[$abstract])) {
            $this->instances[$abstract] = $closure($this->instances[$abstract], $this);
            $this->rebound($abstract);
        } else {
            $this->extenders[$abstract][] = $closure;
            
            if ($this->resolved($abstract)) {
                $this->rebound($abstract);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function tag($abstracts, string $tag): void
    {
        $abstracts = is_array($abstracts) ? $abstracts : [$abstracts];

        foreach ($abstracts as $abstract) {
            $this->tags[$tag][] = $abstract;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function tagged(string $tag): array
    {
        $results = [];

        if (isset($this->tags[$tag])) {
            foreach ($this->tags[$tag] as $abstract) {
                $results[] = $this->make($abstract);
            }
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function refresh(string $abstract): void
    {
        $this->dropStaleInstances($abstract);
        $this->rebound($abstract);
    }

    /**
     * 为服务设置别名
     * 
     * @param string $abstract 抽象标识
     * @param string $alias 别名
     */
    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * 解析服务
     */
    private function resolve(string $abstract, array $parameters = [], bool $raiseEvents = true)
    {
        $abstract = $this->getAlias($abstract);

        // 检查是否为延迟服务
        if (isset($this->deferredServices[$abstract])) {
            $this->loadDeferredProvider($abstract);
        }

        $needsContextualBuild = !empty($parameters) || !is_null($this->getContextualConcrete($abstract));

        if (isset($this->instances[$abstract]) && !$needsContextualBuild) {
            return $this->instances[$abstract];
        }

        $concrete = $this->getConcrete($abstract);

        if ($this->isBuildable($concrete, $abstract)) {
            $object = $this->build($concrete, $parameters);
        } else {
            $object = $this->make($concrete, $parameters);
        }

        foreach ($this->getExtenders($abstract) as $extender) {
            $object = $extender($object, $this);
        }

        if ($this->isShared($abstract) && !$needsContextualBuild) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * 构建实例
     */
    private function build($concrete, array $parameters = [])
    {
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameters);
        }

        try {
            $reflector = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            throw new ContainerException("Target class [{$concrete}] does not exist.", 0, $e);
        }

        if (!$reflector->isInstantiable()) {
            throw new ContainerException("Target [{$concrete}] is not instantiable.");
        }

        $this->buildStack[] = $concrete;

        // 检测循环依赖
        if ($this->hasCircularDependency($concrete)) {
            array_pop($this->buildStack);
            throw new ContainerException("Circular dependency detected while building [{$concrete}].");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            array_pop($this->buildStack);
            return new $concrete;
        }

        $dependencies = $constructor->getParameters();

        try {
            $instances = $this->resolveDependencies($dependencies, $parameters);
        } catch (ContainerException $e) {
            array_pop($this->buildStack);
            throw $e;
        }

        array_pop($this->buildStack);

        return $reflector->newInstanceArgs($instances);
    }

    /**
     * 解析依赖
     */
    private function resolveDependencies(array $dependencies, array $parameters): array
    {
        $results = [];

        foreach ($dependencies as $dependency) {
            if ($this->hasParameterOverride($dependency, $parameters)) {
                $results[] = $parameters[$dependency->getName()];
                continue;
            }

            $results[] = $this->resolveDependency($dependency);
        }

        return $results;
    }

    /**
     * 解析单个依赖
     */
    private function resolveDependency(ReflectionParameter $parameter)
    {
        if ($parameter->getClass()) {
            return $this->resolveClass($parameter);
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new ContainerException("Unresolvable dependency resolving [{$parameter}]");
    }

    /**
     * 解析类依赖
     */
    private function resolveClass(ReflectionParameter $parameter)
    {
        try {
            return $this->make($parameter->getClass()->name);
        } catch (ContainerException $e) {
            if ($parameter->isOptional()) {
                return $parameter->getDefaultValue();
            }
            throw $e;
        }
    }

    /**
     * 检查是否有参数覆盖
     */
    private function hasParameterOverride(ReflectionParameter $dependency, array $parameters): bool
    {
        return array_key_exists($dependency->getName(), $parameters);
    }

    /**
     * 获取具体实现
     */
    private function getConcrete(string $abstract)
    {
        if (!is_null($concrete = $this->getContextualConcrete($abstract))) {
            return $concrete;
        }

        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        return $abstract;
    }

    /**
     * 获取上下文具体实现
     */
    private function getContextualConcrete(string $abstract)
    {
        // 这里可以实现上下文绑定逻辑
        return null;
    }

    /**
     * 检查是否可构建
     */
    private function isBuildable($concrete, string $abstract): bool
    {
        return $concrete === $abstract || $concrete instanceof Closure;
    }

    /**
     * 获取闭包
     */
    private function getClosure(string $abstract, string $concrete): Closure
    {
        return function ($container, $parameters = []) use ($abstract, $concrete) {
            if ($abstract === $concrete) {
                return $container->build($concrete, $parameters);
            }

            return $container->resolve($concrete, $parameters);
        };
    }

    /**
     * 获取扩展器
     */
    private function getExtenders(string $abstract): array
    {
        $abstract = $this->getAlias($abstract);

        return $this->extenders[$abstract] ?? [];
    }

    /**
     * 获取别名
     */
    private function getAlias(string $abstract): string
    {
        if (!isset($this->aliases[$abstract])) {
            return $abstract;
        }

        return $this->getAlias($this->aliases[$abstract]);
    }

    /**
     * 检查是否为别名
     */
    private function isAlias(string $name): bool
    {
        return isset($this->aliases[$name]);
    }

    /**
     * 移除抽象别名
     */
    private function removeAbstractAlias(string $searched): void
    {
        if (!isset($this->aliases[$searched])) {
            return;
        }

        foreach ($this->aliases as $abstract => $alias) {
            if ($alias === $searched) {
                unset($this->aliases[$abstract]);
            }
        }
    }

    /**
     * 删除过期实例
     */
    private function dropStaleInstances(string $abstract): void
    {
        unset($this->instances[$abstract], $this->aliases[$abstract]);
    }

    /**
     * 检查是否已解析
     */
    private function resolved(string $abstract): bool
    {
        if ($this->isAlias($abstract)) {
            $abstract = $this->getAlias($abstract);
        }

        return isset($this->instances[$abstract]);
    }

    /**
     * 重新绑定
     */
    private function rebound(string $abstract): void
    {
        $instance = $this->make($abstract);
        
        // 这里可以触发重新绑定事件
    }

    /**
     * 注册服务提供者
     * 
     * @param ServiceProvider|string $provider
     * @return ServiceProvider
     */
    public function register($provider): ServiceProvider
    {
        if (is_string($provider)) {
            $provider = new $provider($this);
        }

        if (($registered = $this->getProvider($provider)) && !$provider->isDeferred()) {
            return $registered;
        }

        if ($provider->isDeferred()) {
            $this->addDeferredServices($provider);
        }

        $this->serviceProviders[] = $provider;

        $provider->register();

        return $provider;
    }

    /**
     * 获取服务提供者
     * 
     * @param ServiceProvider|string $provider
     * @return ServiceProvider|null
     */
    public function getProvider($provider): ?ServiceProvider
    {
        $name = is_string($provider) ? $provider : get_class($provider);

        foreach ($this->serviceProviders as $serviceProvider) {
            if (get_class($serviceProvider) === $name) {
                return $serviceProvider;
            }
        }

        return null;
    }

    /**
     * 启动服务提供者
     * 
     * @param ServiceProvider $provider
     */
    public function bootProvider(ServiceProvider $provider): void
    {
        if (method_exists($provider, 'boot')) {
            $provider->boot();
        }

        $this->loadedProviders[get_class($provider)] = true;
    }

    /**
     * 启动所有服务提供者
     */
    public function boot(): void
    {
        foreach ($this->serviceProviders as $provider) {
            if (!$this->isProviderLoaded($provider)) {
                $this->bootProvider($provider);
            }
        }
    }

    /**
     * 检查提供者是否已启动
     * 
     * @param ServiceProvider $provider
     * @return bool
     */
    private function isProviderLoaded(ServiceProvider $provider): bool
    {
        return isset($this->loadedProviders[get_class($provider)]);
    }

    /**
     * 添加延迟服务
     * 
     * @param ServiceProvider $provider
     */
    private function addDeferredServices(ServiceProvider $provider): void
    {
        foreach ($provider->provides() as $service) {
            $this->deferredServices[$service] = get_class($provider);
        }
    }

    /**
     * 加载延迟提供者
     * 
     * @param string $service
     */
    private function loadDeferredProvider(string $service): void
    {
        if (!isset($this->deferredServices[$service])) {
            return;
        }

        $provider = $this->deferredServices[$service];

        if (!isset($this->loadedProviders[$provider])) {
            $this->register($provider);
            $this->bootProvider($this->getProvider($provider));
        }
    }

    /**
     * 检测循环依赖
     * 
     * @param string $concrete
     * @return bool
     */
    private function hasCircularDependency(string $concrete): bool
    {
        $count = array_count_values($this->buildStack);
        return isset($count[$concrete]) && $count[$concrete] > 1;
    }

    /**
     * 获取构建栈信息
     * 
     * @return array
     */
    public function getBuildStack(): array
    {
        return $this->buildStack;
    }

    /**
     * 清空构建栈
     */
    public function clearBuildStack(): void
    {
        $this->buildStack = [];
    }
}

/**
 * 绑定方法调用器
 */
class BoundMethod
{
    /**
     * 调用绑定方法
     */
    public static function call(ContainerInterface $container, $callback, array $parameters = [])
    {
        if (is_string($callback) && strpos($callback, '::') !== false) {
            $callback = explode('::', $callback, 2);
        }

        if (is_array($callback)) {
            return static::callClass($container, $callback, $parameters);
        }

        return static::callBoundMethod($container, $callback, $parameters);
    }

    /**
     * 调用类方法
     */
    protected static function callClass(ContainerInterface $container, array $target, array $parameters = [])
    {
        [$class, $method] = $target;

        if (is_string($class)) {
            $class = $container->make($class);
        }

        return call_user_func_array([$class, $method], $parameters);
    }

    /**
     * 调用绑定方法
     */
    protected static function callBoundMethod(ContainerInterface $container, $callback, array $parameters = [])
    {
        return call_user_func_array($callback, $parameters);
    }
}