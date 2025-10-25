# Omise 支付接口列表

## 📋 接口概览

| 接口名称 | 方法 | 路径 | 认证 | 描述 |
|---------|------|------|------|------|
| 获取支付配置 | GET | `/api/payment/config` | ❌ | 获取前端支付配置 |
| 创建支付令牌 | POST | `/api/payment/create-token` | ❌ | 创建 Omise 支付令牌 |
| 处理支付 | POST | `/api/payment/process` | ✅ | 处理支付请求 |
| 获取支付详情 | GET | `/api/payment/charge/{id}` | ✅ | 获取支付详情 |
| 退款 | POST | `/api/payment/refund` | ✅ | 处理退款 |
| Webhook 处理 | POST | `/api/payment/webhook` | ❌ | 处理 Webhook 事件 |

## 🔧 配置文件

### 环境变量
```env
# Omise 配置
OMISE_PUBLIC_KEY=pkey_test_65ggqd9jdlaax89pkex
OMISE_SECRET_KEY=skey_test_65ggqda75e2fzsxfvty
OMISE_ENVIRONMENT=test
OMISE_DEFAULT_CURRENCY=THB
OMISE_WEBHOOK_SECRET=your_webhook_secret
```

### 配置文件 (`config/omise.php`)
```php
return [
    'public_key' => env('OMISE_PUBLIC_KEY'),
    'secret_key' => env('OMISE_SECRET_KEY'),
    'environment' => env('OMISE_ENVIRONMENT', 'test'),
    'default_currency' => env('OMISE_DEFAULT_CURRENCY', 'THB'),
    'supported_currencies' => ['THB', 'USD', 'EUR', 'JPY', 'SGD'],
    'payment_methods' => ['credit_card', 'bank_transfer', 'convenience_store', 'internet_banking'],
    'webhook' => [
        'enabled' => env('OMISE_WEBHOOK_ENABLED', true),
        'url' => env('OMISE_WEBHOOK_URL', '/api/payment/webhook'),
        'secret' => env('OMISE_WEBHOOK_SECRET'),
    ],
];
```

## 🌐 接口详情

### 1. 获取支付配置
```http
GET /api/payment/config
```

**响应:**
```json
{
  "success": true,
  "data": {
    "public_key": "pkey_test_65ggqd9jdlaax89pkex",
    "environment": "test",
    "supported_currencies": ["THB", "USD", "EUR", "JPY", "SGD"],
    "payment_methods": ["credit_card", "bank_transfer", "convenience_store", "internet_banking"]
  }
}
```

### 2. 创建支付令牌
```http
POST /api/payment/create-token
Content-Type: application/json

{
  "name": "John Doe",
  "number": "4242424242424242",
  "expiration_month": "12",
  "expiration_year": "2025",
  "security_code": "123"
}
```

**响应:**
```json
{
  "success": true,
  "data": {
    "token_id": "tokn_test_xxx"
  }
}
```

### 3. 处理支付
```http
POST /api/payment/process
Authorization: Bearer {token}
Content-Type: application/json

{
  "token": "tokn_test_xxx",
  "amount": 100,
  "currency": "THB",
  "description": "教育费用"
}
```

**响应:**
```json
{
  "success": true,
  "data": {
    "charge_id": "chrg_test_xxx",
    "status": "successful",
    "amount": 100,
    "currency": "THB",
    "transaction_id": "trxn_test_xxx"
  }
}
```

### 4. 获取支付详情
```http
GET /api/payment/charge/{chargeId}
Authorization: Bearer {token}
```

**响应:**
```json
{
  "success": true,
  "data": {
    "id": "chrg_test_xxx",
    "status": "successful",
    "amount": 10000,
    "currency": "THB",
    "description": "教育费用",
    "created": "2024-01-16T10:30:00Z",
    "transaction": "trxn_test_xxx"
  }
}
```

### 5. 退款
```http
POST /api/payment/refund
Authorization: Bearer {token}
Content-Type: application/json

{
  "charge_id": "chrg_test_xxx",
  "amount": 50
}
```

**响应:**
```json
{
  "success": true,
  "data": {
    "refund_id": "rfnd_test_xxx"
  }
}
```

### 6. Webhook 处理
```http
POST /api/payment/webhook
X-Omise-Signature: {signature}
Content-Type: application/json

{
  "type": "charge.complete",
  "data": {
    "id": "chrg_test_xxx",
    "status": "successful",
    "amount": 10000
  }
}
```

**响应:**
```json
{
  "status": "ok"
}
```

## 🔧 服务类方法

### OmiseService 类方法

| 方法名 | 参数 | 返回值 | 描述 |
|--------|------|--------|------|
| `createToken()` | `array $cardData` | `array` | 创建支付令牌 |
| `processPayment()` | `array $paymentData` | `array` | 处理支付 |
| `getCharge()` | `string $chargeId` | `array` | 获取支付详情 |
| `refund()` | `string $chargeId, int $amount` | `array` | 处理退款 |
| `verifyWebhook()` | `string $payload, string $signature` | `bool` | 验证 Webhook 签名 |
| `getPublicKey()` | - | `string` | 获取公钥 |
| `isTestEnvironment()` | - | `bool` | 检查环境 |

## 🔐 安全特性

### 认证机制
- **Bearer Token**: 支付处理接口需要认证
- **Webhook 签名**: 通过 HMAC-SHA256 验证来源

### 数据验证
- **输入验证**: 所有参数都经过验证
- **金额验证**: 确保金额为正数
- **货币验证**: 只允许支持的货币

### 错误处理
- **统一响应**: 所有接口返回统一格式
- **错误日志**: 详细记录错误信息
- **异常处理**: 安全的异常处理机制

## 📊 支持的货币和支付方式

### 支持货币
- **THB**: 泰铢 (默认)
- **USD**: 美元
- **EUR**: 欧元
- **JPY**: 日元
- **SGD**: 新加坡元

### 支付方式
- **credit_card**: 信用卡
- **bank_transfer**: 银行转账
- **convenience_store**: 便利店支付
- **internet_banking**: 网银支付

## 🚀 快速使用

### 1. 前端集成
```javascript
// 获取支付配置
const config = await fetch('/api/payment/config').then(r => r.json());

// 创建支付令牌
const token = await fetch('/api/payment/create-token', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(cardData)
}).then(r => r.json());

// 处理支付
const payment = await fetch('/api/payment/process', {
  method: 'POST',
  headers: { 
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${userToken}`
  },
  body: JSON.stringify({
    token: token.data.token_id,
    amount: 100,
    currency: 'THB'
  })
}).then(r => r.json());
```

### 2. 后端调用
```php
// 使用 OmiseService
$omiseService = app(OmiseService::class);

// 创建令牌
$tokenResult = $omiseService->createToken($cardData);

// 处理支付
$paymentResult = $omiseService->processPayment($paymentData);

// 获取支付详情
$chargeResult = $omiseService->getCharge($chargeId);
```

## 📈 监控和日志

### 支付日志
- 支付成功/失败记录
- 用户操作记录
- 异常情况记录

### 性能监控
- 支付处理时间
- 成功率统计
- 错误率分析

---

**注意**: 这是 `api-system` 中 Omise 支付接口的简化列表，包含所有接口的基本信息、配置和使用方法。详细文档请参考 `OMISE_API_DOCUMENTATION.md`。
