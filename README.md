# API System - Laravel API 系统

## 📋 系统概述

基于 Laravel 的 RESTful API 系统，为前端应用提供完整的数据接口，包括用户认证、课程管理、学生管理、账单管理和支付功能。

## 🎯 核心功能

### 1. 用户认证系统
- **JWT Token 认证**: 自定义 JWT 实现
- **多角色支持**: 教师和学生角色
- **跨系统认证**: 与 admin-system 集成

### 2. 课程管理 API
- 课程 CRUD 操作
- 教师课程关联
- 课程学生管理
- 课程统计功能

### 3. 学生管理 API
- 学生信息管理
- 学生课程查询
- 学生账单查询
- 学生统计功能

### 4. 账单管理 API
- 账单 CRUD 操作
- 支付状态管理
- 账单统计功能
- 支付历史查询

### 5. 支付集成
- **Omise 支付网关**: 信用卡支付
- **支付状态跟踪**: 实时状态更新
- **退款功能**: 支持账单退款

### 6. 仪表盘统计
- 实时数据统计
- 角色权限控制
- 多维度统计

## 🏗️ 技术架构

### 核心技术
- **Laravel 11**: PHP 框架
- **PostgreSQL**: 数据库
- **JWT**: 自定义认证
- **Omise**: 支付网关
- **Swagger**: API 文档

### 项目结构
```
app/
├── Http/
│   ├── Controllers/          # API 控制器
│   │   ├── AuthController.php        # 认证控制器
│   │   ├── DashboardController.php  # 仪表盘控制器
│   │   ├── CourseController.php      # 课程控制器
│   │   ├── StudentController.php    # 学生控制器
│   │   ├── InvoiceController.php    # 账单控制器
│   │   └── PaymentController.php     # 支付控制器
│   └── Middleware/           # 中间件
│       └── VerifyApiToken.php       # JWT 验证中间件
├── Services/                 # 业务服务
│   └── OmiseService.php     # Omise 支付服务
├── Models/                   # 数据模型
└── Console/                  # 命令行工具
```

## 🔐 认证系统

### JWT Token 结构
```json
{
  "user_id": 1,
  "username": "teacher01",
  "name": "张老师",
  "email": "teacher@example.com",
  "role": "teacher",
  "exp": 1640995200,
  "iat": 1640908800
}
```

### 认证流程
1. 用户登录获取 Token
2. 前端存储 Token
3. 请求时携带 Token
4. 中间件验证 Token
5. 返回用户信息

### 中间件实现
```php
class VerifyApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json(['message' => '未提供访问令牌'], 401);
        }
        
        try {
            // 验证 JWT Token
            $payload = $this->verifyToken($token);
            $request->attributes->set('auth_user', $payload);
            
        } catch (\Exception $e) {
            return response()->json(['message' => '无效的访问令牌'], 401);
        }
        
        return $next($request);
    }
}
```

## 📊 API 接口文档

### 认证接口

#### 用户登录
```http
POST /api/auth/login
Content-Type: application/json

{
  "username": "teacher01",
  "password": "password123",
  "role": "teacher"
}
```

**响应:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "username": "teacher01",
      "name": "张老师",
      "email": "teacher@example.com",
      "role": "teacher"
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
  }
}
```

#### 获取用户信息
```http
GET /api/auth/me
Authorization: Bearer {token}
```

#### 用户登出
```http
POST /api/auth/logout
Authorization: Bearer {token}
```

### 仪表盘接口

#### 获取统计数据
```http
GET /api/dashboard/stats
Authorization: Bearer {token}
```

**响应:**
```json
{
  "success": true,
  "data": {
    "teachers_count": 15,
    "students_count": 120,
    "courses_count": 8,
    "invoices_count": 45,
    "pending_invoices": 12,
    "paid_invoices": 33
  }
}
```

### 课程管理接口

#### 获取课程列表
```http
GET /api/courses?page=1&per_page=10
Authorization: Bearer {token}
```

#### 创建课程
```http
POST /api/courses
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "数学课程",
  "year_month": "202401",
  "fee": 1000.00
}
```

#### 更新课程
```http
PUT /api/courses/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "数学课程（更新）",
  "fee": 1200.00
}
```

#### 删除课程
```http
DELETE /api/courses/{id}
Authorization: Bearer {token}
```

### 学生管理接口

#### 获取学生列表
```http
GET /api/students?page=1&per_page=10
Authorization: Bearer {token}
```

#### 创建学生
```http
POST /api/students
Authorization: Bearer {token}
Content-Type: application/json

{
  "username": "student01",
  "name": "张三",
  "email": "student@example.com",
  "password": "password123"
}
```

#### 获取学生课程
```http
GET /api/students/{id}/courses
Authorization: Bearer {token}
```

#### 获取学生账单
```http
GET /api/students/{id}/invoices
Authorization: Bearer {token}
```

### 账单管理接口

#### 获取账单列表
```http
GET /api/invoices?page=1&per_page=10
Authorization: Bearer {token}
```

#### 创建账单
```http
POST /api/invoices
Authorization: Bearer {token}
Content-Type: application/json

{
  "student_id": 1,
  "course_id": 1,
  "amount": 1000.00
}
```

#### 更新账单
```http
PUT /api/invoices/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "status": 1
}
```

### 支付接口

#### 获取支付配置
```http
GET /api/payment/config
```

**响应:**
```json
{
  "success": true,
  "data": {
    "public_key": "pkey_test_65ggqd9jdlaax89pkex",
    "environment": "test"
  }
}
```

#### 处理支付
```http
POST /api/payment/process
Authorization: Bearer {token}
Content-Type: application/json

{
  "invoice_id": 1,
  "token": "tokn_test_1234567890",
  "amount": 1000.00
}
```

#### 获取支付详情
```http
GET /api/payment/charge/{chargeId}
Authorization: Bearer {token}
```

## 💳 支付集成

### Omise 配置
```php
// config/omise.php
return [
    'public_key' => env('OMISE_PUBLIC_KEY'),
    'secret_key' => env('OMISE_SECRET_KEY'),
    'environment' => env('OMISE_ENVIRONMENT', 'test'),
];
```

### 支付服务
```php
class OmiseService
{
    public function createCharge($amount, $token, $currency = 'THB')
    {
        \Omise\Omise::setApiKey(config('omise.secret_key'));
        
        return \Omise\Charge::create([
            'amount' => $amount * 100, // Omise 使用分作为单位
            'currency' => $currency,
            'card' => $token,
        ]);
    }
}
```

## 🗄️ 数据库设计

### 核心表结构

#### users (用户表)
```sql
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role INTEGER DEFAULT 0, -- 0=student, 1=teacher
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP
);
```

#### courses (课程表)
```sql
CREATE TABLE courses (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    year_month VARCHAR(6) NOT NULL,
    fee DECIMAL(10,2) NOT NULL,
    teacher_id BIGINT NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP
);
```

#### invoices (账单表)
```sql
CREATE TABLE invoices (
    id BIGSERIAL PRIMARY KEY,
    student_id BIGINT NOT NULL,
    course_id BIGINT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status INTEGER DEFAULT 0, -- 0=pending, 1=paid
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP
);
```

## 🔧 核心功能实现

### 1. 角色权限控制

#### 教师权限
- 管理自己的课程
- 查看所有学生
- 创建和管理账单
- 查看统计数据

#### 学生权限
- 查看所有课程
- 查看自己的账单
- 进行支付操作
- 查看个人统计

### 2. 数据统计

#### 仪表盘统计
```php
public function getStats(Request $request)
{
    $user = $request->attributes->get('auth_user');
    
    // 根据角色返回不同统计
    if ($user->role === 'teacher') {
        // 教师统计逻辑
    } elseif ($user->role === 'student') {
        // 学生统计逻辑
    }
    
    return response()->json([
        'success' => true,
        'data' => $stats
    ]);
}
```

### 3. 支付处理

#### 支付流程
1. 前端获取支付 Token
2. 调用支付处理接口
3. 创建 Omise Charge
4. 更新账单状态
5. 返回支付结果

## 🚀 部署配置

### 环境配置
```env
# 数据库配置
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=education_api
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Omise 配置
OMISE_PUBLIC_KEY=pkey_test_65ggqd9jdlaax89pkex
OMISE_SECRET_KEY=skey_test_65ggqda75e2fzsxfvty
OMISE_ENVIRONMENT=test

# 应用配置
APP_URL=http://api.localhost
```

### 安装步骤
```bash
# 1. 安装依赖
composer install

# 2. 环境配置
cp .env.example .env
php artisan key:generate

# 3. 数据库迁移
php artisan migrate

# 4. 数据填充
php artisan db:seed

# 5. 生成 API 文档
php artisan l5-swagger:generate
```

## 📚 API 文档

### Swagger 集成
- 自动生成 API 文档
- 在线测试接口
- 接口规范说明

### 访问文档
```
http://api.localhost/api/docs
```

## 🔒 安全措施

### 数据安全
- JWT Token 验证
- 请求频率限制
- 输入数据验证
- SQL 注入防护

### API 安全
- CORS 配置
- 请求头验证
- 错误信息过滤

## 📈 性能优化

### 数据库优化
- 查询优化
- 索引优化
- 连接池配置

### 缓存策略
- Redis 缓存
- 查询结果缓存
- 静态资源缓存

## 🐛 故障排除

### 常见问题
1. **认证失败**: 检查 Token 格式
2. **权限错误**: 检查用户角色
3. **支付失败**: 检查 Omise 配置

### 调试方法
- 启用 API 日志
- 使用 Postman 测试
- 查看错误日志

## 📊 监控和维护

### 日志监控
- API 请求日志
- 错误日志
- 性能监控

### 数据备份
- 定期数据库备份
- 配置文件备份
- 用户数据备份

---

**注意**: 这是基于 Laravel 的 RESTful API 系统，提供完整的教育管理功能，包括用户认证、课程管理、学生管理、账单管理和支付功能。系统具有良好的安全性和可扩展性。

Public key
pkey_test_65ggqd9jdlaax89pkex

Secret key
skey_test_65ggqda75e2fzsxfvty