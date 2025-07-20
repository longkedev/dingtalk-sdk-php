# 钉钉 PHP SDK Makefile

.PHONY: help install test test-unit test-integration test-feature test-coverage cs-check cs-fix phpstan quality clean docs

# 默认目标
help: ## 显示帮助信息
	@echo "钉钉 PHP SDK 开发工具"
	@echo ""
	@echo "可用命令:"
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

# 安装依赖
install: ## 安装 Composer 依赖
	composer install

install-dev: ## 安装开发依赖
	composer install --dev

update: ## 更新依赖
	composer update

# 测试相关
test: ## 运行所有测试
	composer test

test-unit: ## 运行单元测试
	composer test-unit

test-integration: ## 运行集成测试
	composer test-integration

test-feature: ## 运行功能测试
	composer test-feature

test-coverage: ## 生成测试覆盖率报告
	composer test-coverage

test-watch: ## 监听文件变化并自动运行测试
	vendor/bin/phpunit-watcher watch

# 代码质量
cs-check: ## 检查代码风格
	composer cs-check

cs-fix: ## 修复代码风格
	composer cs-fix

phpstan: ## 运行静态分析
	composer phpstan

quality: ## 运行所有质量检查
	composer quality

# 安全检查
security: ## 运行安全检查
	composer audit

# 文档生成
docs: ## 生成 API 文档
	@if [ -f "phpdoc.xml" ]; then \
		vendor/bin/phpdoc; \
		echo "API 文档已生成到 docs/ 目录"; \
	else \
		echo "phpdoc.xml 配置文件不存在"; \
	fi

# 清理
clean: ## 清理生成的文件
	rm -rf vendor/
	rm -rf coverage/
	rm -rf docs/
	rm -rf .phpunit.result.cache

clean-cache: ## 清理缓存
	rm -rf .phpunit.result.cache
	composer clear-cache

# 开发环境
dev-setup: install-dev ## 设置开发环境
	@echo "开发环境设置完成"
	@echo "运行 'make test' 来验证安装"

# 发布准备
pre-release: quality test ## 发布前检查
	@echo "所有检查通过，可以发布"

# 示例运行
example: ## 运行基础示例
	php examples/basic_usage.php

# 基准测试
benchmark: ## 运行性能基准测试
	@if [ -f "benchmark/run.php" ]; then \
		php benchmark/run.php; \
	else \
		echo "基准测试文件不存在"; \
	fi

# Git 钩子
git-hooks: ## 安装 Git 钩子
	@if [ -d ".git" ]; then \
		cp scripts/pre-commit .git/hooks/; \
		chmod +x .git/hooks/pre-commit; \
		echo "Git 钩子已安装"; \
	else \
		echo "不是 Git 仓库"; \
	fi

# 项目统计
stats: ## 显示项目统计信息
	@echo "=== 项目统计 ==="
	@echo "代码行数:"
	@find src -name "*.php" | xargs wc -l | tail -1
	@echo ""
	@echo "测试行数:"
	@find tests -name "*.php" | xargs wc -l | tail -1
	@echo ""
	@echo "文件数量:"
	@echo "  源代码: $$(find src -name "*.php" | wc -l)"
	@echo "  测试: $$(find tests -name "*.php" | wc -l)"
	@echo "  示例: $$(find examples -name "*.php" | wc -l)"

# 依赖检查
deps-check: ## 检查依赖更新
	composer outdated

deps-update: ## 更新依赖到最新版本
	composer update

# 代码分析
analyze: ## 运行代码分析
	@echo "运行 PHPStan..."
	composer phpstan
	@echo ""
	@echo "检查代码风格..."
	composer cs-check
	@echo ""
	@echo "运行安全检查..."
	composer audit

# 完整检查
check-all: analyze test ## 运行所有检查
	@echo "所有检查完成"

# 快速修复
fix: cs-fix ## 快速修复代码风格问题

# 监听模式
watch: ## 监听文件变化
	@echo "监听文件变化中... (Ctrl+C 退出)"
	@while true; do \
		inotifywait -r -e modify src/ tests/ 2>/dev/null && \
		echo "文件已修改，运行测试..." && \
		make test-unit; \
	done

# 版本信息
version: ## 显示版本信息
	@echo "PHP 版本: $$(php -v | head -1)"
	@echo "Composer 版本: $$(composer --version)"
	@if [ -f "composer.json" ]; then \
		echo "项目版本: $$(grep '"version"' composer.json | cut -d'"' -f4)"; \
	fi