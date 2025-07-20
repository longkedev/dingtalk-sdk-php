# 贡献指南

感谢您对钉钉 PHP SDK 项目的关注！我们欢迎所有形式的贡献，包括但不限于：

- 🐛 报告 Bug
- 💡 提出新功能建议
- 📝 改进文档
- 🔧 提交代码修复
- ✨ 添加新功能
- 🧪 编写测试
- 📖 翻译文档

## 开始之前

在开始贡献之前，请确保您已经：

1. 阅读了项目的 [README.md](README.md)
2. 了解了项目的架构和设计理念
3. 熟悉了 [代码规范](#代码规范)
4. 搭建了 [开发环境](#开发环境搭建)

## 贡献方式

### 报告 Bug

如果您发现了 Bug，请通过以下步骤报告：

1. 在 [Issues](https://github.com/longkedev/dingtalk-sdk-php/issues) 中搜索是否已有相同问题
2. 如果没有，请创建新的 Issue
3. 使用 Bug 报告模板，提供详细信息：
   - 问题描述
   - 复现步骤
   - 期望行为
   - 实际行为
   - 环境信息（PHP版本、操作系统等）
   - 相关代码片段或错误日志

### 提出功能建议

如果您有新功能的想法：

1. 在 [Issues](https://github.com/longkedev/dingtalk-sdk-php/issues) 中搜索是否已有相同建议
2. 创建新的 Feature Request Issue
3. 详细描述：
   - 功能描述
   - 使用场景
   - 预期收益
   - 可能的实现方案

### 提交代码

#### 1. Fork 项目

点击项目页面右上角的 "Fork" 按钮，将项目 Fork 到您的 GitHub 账户。

#### 2. 克隆代码

```bash
git clone https://github.com/YOUR_USERNAME/dingtalk-sdk-php.git
cd dingtalk-sdk-php
```

#### 3. 创建分支

```bash
# 创建并切换到新分支
git checkout -b feature/your-feature-name

# 或者修复 Bug
git checkout -b fix/your-bug-fix
```

#### 4. 进行开发

请遵循以下原则：

- 保持代码简洁、可读
- 添加必要的注释
- 编写测试用例
- 更新相关文档

#### 5. 提交代码

```bash
# 添加文件
git add .

# 提交代码（请使用有意义的提交信息）
git commit -m "feat: 添加用户批量操作功能"

# 推送到您的 Fork
git push origin feature/your-feature-name
```

#### 6. 创建 Pull Request

1. 访问您的 Fork 页面
2. 点击 "New Pull Request"
3. 选择目标分支（通常是 `main`）
4. 填写 PR 描述，包括：
   - 变更内容
   - 相关 Issue
   - 测试说明
   - 截图（如适用）

## 开发环境搭建

### 系统要求

- PHP >= 8.0
- Composer
- Git

### 安装依赖

```bash
# 安装 Composer 依赖
composer install

# 安装开发依赖
composer install --dev
```

### 运行测试

```bash
# 运行所有测试
composer test

# 运行单元测试
composer test-unit

# 运行集成测试
composer test-integration

# 运行功能测试
composer test-feature

# 生成测试覆盖率报告
composer test-coverage
```

### 代码质量检查

```bash
# 检查代码风格
composer cs-check

# 修复代码风格
composer cs-fix

# 运行静态分析
composer phpstan

# 运行所有质量检查
composer quality
```

## 代码规范

### 编码标准

- 遵循 [PSR-12](https://www.php-fig.org/psr/psr-12/) 编码标准
- 使用 4 个空格进行缩进
- 行尾不要有多余的空格
- 文件末尾要有一个空行

### 命名规范

#### 类名
- 使用 PascalCase（大驼峰）
- 类名应该清晰地表达其用途

```php
class UserService
class ConfigManager
class DingTalkException
```

#### 方法名
- 使用 camelCase（小驼峰）
- 方法名应该是动词或动词短语

```php
public function getUserInfo()
public function createUser()
public function validateParameters()
```

#### 变量名
- 使用 camelCase（小驼峰）
- 变量名应该有意义

```php
$userId = 'user123';
$departmentList = [];
$apiResponse = $this->httpClient->get($url);
```

#### 常量名
- 使用 UPPER_SNAKE_CASE
- 常量应该在类的顶部定义

```php
const DEFAULT_TIMEOUT = 30;
const API_VERSION_V1 = 'v1';
const CACHE_PREFIX = 'dingtalk_';
```

### 注释规范

#### 类注释

```php
/**
 * 用户服务类
 * 
 * 提供用户管理相关的API操作，包括获取用户信息、创建用户、更新用户等功能
 * 
 * @package DingTalk\Services
 * @author longkedev <longkedev@gmail.com>
 * @since 1.0.0
 */
class UserService extends BaseService
{
    // ...
}
```

#### 方法注释

```php
/**
 * 获取用户详细信息
 * 
 * @param string $userId 用户ID
 * @param bool $useCache 是否使用缓存
 * @return array 用户信息数组
 * @throws ApiException 当API调用失败时
 * @throws AuthException 当认证失败时
 * 
 * @example
 * $userInfo = $userService->getUserInfo('user123');
 * print_r($userInfo);
 */
public function getUserInfo(string $userId, bool $useCache = true): array
{
    // ...
}
```

#### 属性注释

```php
/**
 * HTTP客户端实例
 * 
 * @var HttpClient
 */
private HttpClient $httpClient;

/**
 * 缓存管理器
 * 
 * @var CacheManager
 */
private CacheManager $cache;
```

### 错误处理

#### 异常类型

- 使用具体的异常类型
- 提供有意义的错误消息
- 包含必要的上下文信息

```php
// 好的做法
throw new ApiException('获取用户信息失败', 40001, $response);

// 不好的做法
throw new Exception('错误');
```

#### 异常处理

```php
try {
    $result = $this->apiCall($url, $params);
} catch (HttpException $e) {
    $this->logger->error('HTTP请求失败', [
        'url' => $url,
        'params' => $params,
        'error' => $e->getMessage(),
    ]);
    throw new ApiException('API调用失败: ' . $e->getMessage(), 0, $e);
}
```

### 测试规范

#### 测试文件命名

- 单元测试：`ClassNameTest.php`
- 集成测试：`ServiceIntegrationTest.php`
- 功能测试：`FeatureNameTest.php`

#### 测试方法命名

```php
public function testGetUserInfoReturnsCorrectData()
public function testCreateUserWithValidDataSucceeds()
public function testGetUserInfoWithInvalidUserIdThrowsException()
```

#### 测试结构

```php
public function testMethodName()
{
    // Arrange - 准备测试数据
    $userId = 'user123';
    $expectedData = ['name' => 'Test User'];
    
    // Act - 执行被测试的方法
    $result = $this->userService->getUserInfo($userId);
    
    // Assert - 验证结果
    $this->assertEquals($expectedData, $result);
}
```

## 提交信息规范

我们使用 [Conventional Commits](https://www.conventionalcommits.org/) 规范：

### 格式

```
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

### 类型

- `feat`: 新功能
- `fix`: Bug 修复
- `docs`: 文档更新
- `style`: 代码格式修改（不影响代码逻辑）
- `refactor`: 代码重构
- `perf`: 性能优化
- `test`: 测试相关
- `chore`: 构建过程或辅助工具的变动

### 示例

```bash
feat(user): 添加批量获取用户信息功能

添加了 getUserInfoBatch 方法，支持一次性获取多个用户的详细信息，
提高了批量操作的效率。

Closes #123
```

```bash
fix(cache): 修复缓存键冲突问题

修复了在多应用环境下缓存键可能冲突的问题，
现在会自动添加应用标识作为前缀。

Fixes #456
```

## 发布流程

### 版本号规范

我们遵循 [语义化版本](https://semver.org/lang/zh-CN/) 规范：

- `MAJOR.MINOR.PATCH`
- 主版本号：不兼容的 API 修改
- 次版本号：向下兼容的功能性新增
- 修订号：向下兼容的问题修正

### 发布检查清单

在发布新版本之前，请确保：

- [ ] 所有测试通过
- [ ] 代码覆盖率达到要求
- [ ] 静态分析无错误
- [ ] 文档已更新
- [ ] CHANGELOG.md 已更新
- [ ] 版本号已更新

## 社区准则

### 行为准则

我们致力于为每个人提供友好、安全和欢迎的环境。请遵循以下准则：

- 使用友好和包容的语言
- 尊重不同的观点和经验
- 优雅地接受建设性批评
- 关注对社区最有利的事情
- 对其他社区成员表示同理心

### 沟通方式

- **GitHub Issues**: 报告 Bug、提出功能建议
- **Pull Requests**: 代码贡献、文档改进
- **Discussions**: 一般性讨论、问题求助

## 获得帮助

如果您在贡献过程中遇到任何问题，可以通过以下方式获得帮助：

1. 查看 [FAQ](docs/FAQ.md)
2. 搜索现有的 [Issues](https://github.com/longkedev/dingtalk-sdk-php/issues)
3. 创建新的 Issue 描述您的问题
4. 在 [Discussions](https://github.com/longkedev/dingtalk-sdk-php/discussions) 中提问

## 致谢

感谢所有为这个项目做出贡献的开发者！您的贡献让这个项目变得更好。

### 贡献者

- [longkedev](https://github.com/longkedev) - 项目创建者和维护者

### 特别感谢

- 钉钉开放平台团队提供的 API 文档和支持
- PHP 社区提供的优秀工具和库
- 所有提供反馈和建议的用户

---

再次感谢您的贡献！🎉