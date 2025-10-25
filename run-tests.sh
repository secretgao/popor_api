#!/bin/bash

# API System 测试运行脚本
# 使用方法: ./run-tests.sh [选项]
# 选项:
#   --unit      只运行单元测试
#   --feature   只运行功能测试
#   --coverage  生成代码覆盖率报告
#   --verbose   详细输出
#   --filter    过滤特定测试

set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 默认参数
SUITE=""
COVERAGE=false
VERBOSE=false
FILTER=""

# 解析命令行参数
while [[ $# -gt 0 ]]; do
    case $1 in
        --unit)
            SUITE="Unit"
            shift
            ;;
        --feature)
            SUITE="Feature"
            shift
            ;;
        --coverage)
            COVERAGE=true
            shift
            ;;
        --verbose)
            VERBOSE=true
            shift
            ;;
        --filter)
            FILTER="$2"
            shift 2
            ;;
        *)
            echo "未知选项: $1"
            exit 1
            ;;
    esac
done

echo -e "${BLUE}🧪 API System 测试套件${NC}"
echo "=================================="

# 检查是否在正确的目录
if [ ! -f "composer.json" ]; then
    echo -e "${RED}❌ 错误: 请在 api-system 项目根目录运行此脚本${NC}"
    exit 1
fi

# 检查依赖
echo -e "${YELLOW}📦 检查依赖...${NC}"
if [ ! -d "vendor" ]; then
    echo -e "${YELLOW}安装 Composer 依赖...${NC}"
    composer install
fi

# 检查测试数据库
echo -e "${YELLOW}🗄️ 检查测试数据库...${NC}"
if ! php artisan migrate:status --env=testing > /dev/null 2>&1; then
    echo -e "${YELLOW}创建测试数据库...${NC}"
    php artisan migrate --env=testing --force
fi

# 构建测试命令
TEST_CMD="DB_DATABASE=education_test php vendor/bin/phpunit"

if [ ! -z "$SUITE" ]; then
    TEST_CMD="$TEST_CMD --testsuite=$SUITE"
fi

if [ "$COVERAGE" = true ]; then
    TEST_CMD="$TEST_CMD --coverage"
fi

if [ "$VERBOSE" = true ]; then
    TEST_CMD="$TEST_CMD --verbose"
fi

if [ ! -z "$FILTER" ]; then
    TEST_CMD="$TEST_CMD --filter=$FILTER"
fi

# 运行测试
echo -e "${GREEN}🚀 开始运行测试...${NC}"
echo "命令: $TEST_CMD"
echo ""

# 执行测试
if eval $TEST_CMD; then
    echo ""
    echo -e "${GREEN}✅ 所有测试通过！${NC}"
    
    if [ "$COVERAGE" = true ]; then
        echo -e "${BLUE}📊 代码覆盖率报告已生成${NC}"
    fi
else
    echo ""
    echo -e "${RED}❌ 测试失败！${NC}"
    exit 1
fi

echo ""
echo -e "${BLUE}📋 测试统计:${NC}"
echo "=================================="

# 显示测试统计
if [ -z "$SUITE" ]; then
    echo "✅ 单元测试 (Unit Tests)"
    echo "✅ 功能测试 (Feature Tests)"
    echo "✅ 集成测试 (Integration Tests)"
else
    echo "✅ $SUITE 测试"
fi

echo ""
echo -e "${GREEN}🎉 测试完成！${NC}"
