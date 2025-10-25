# Omise 支付接口文档

## 📋 概述

本文档整理了 `api-system` 中所有与 Omise 支付相关的接口，包括配置、令牌创建、支付处理、退款和 Webhook 处理。

## 🔧 配置文件

### Omise 配置 (`config/omise.php`)
```php
return [
    // 公钥（前端使用）
    'public_key' => env('OMISE_PUBLIC_KEY', 'pkey_test_65ggqd9jdlaax89pkex'),
    
    // 私钥（后端使用）
    'secret_key' => env('OMISE_SECRET_KEY', 'skey_test_65ggqda75e2fzsxfvty'),
    
    // 环境设置
    'environment' => env('OMISE_ENVIRONMENT', 'test'),
    
    // 默认货币
    'default_currency' => env('OMISE_DEFAULT_CURRENCY', 'THB'),
    
    // 支持的货币
    'supported_currencies' => [
        'THB' => '泰铢',
        'USD' => '美元',
        'EUR' => '欧元',
        'JPY' => '日元',
        'SGD' => '新加坡元',
    ],
    
    // 支付方式
    'payment_methods' => [
        'credit_card' => '信用卡',
        'bank_transfer' => '银行转账',
        'convenience_store' => '便利店支付',
        'internet_banking' => '网银支付',
    ],
    
    // Webhook 设置
    'webhook' => [
        'enabled' => env('OMISE_WEBHOOK_ENABLED', true),
        'url' => env('OMISE_WEBHOOK_URL', '/api/payment/webhook'),
        'secret' => env('OMISE_WEBHOOK_SECRET'),
    ],
];
```

## 🌐 API 接口列表

### 1. 获取支付配置

#### 接口信息
- **URL**: `GET /api/payment/config`
- **认证**: 无需认证
- **描述**: 获取前端支付配置信息

#### 请求示例
```http
GET /api/payment/config
```

#### 响应示例
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

#### 接口信息
- **URL**: `POST /api/payment/create-token`
- **认证**: 无需认证
- **描述**: 创建 Omise 支付令牌

#### 请求参数
```json
{
  "name": "John Doe",
  "number": "4242424242424242",
  "expiration_month": "12",
  "expiration_year": "2025",
  "security_code": "123"
}
```

#### 请求示例
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

#### 响应示例
```json
{
  "success": true,
  "data": {
    "token_id": "tokn_test_xxx"
  }
}
```

#### 错误响应
```json
{
  "success": false,
  "message": "验证失败",
  "errors": {
    "number": ["The number field is required."]
  }
}
```

### 3. 处理支付

#### 接口信息
- **URL**: `POST /api/payment/process`
- **认证**: 需要 Bearer Token
- **描述**: 使用 Omise 处理支付

#### 请求参数
```json
{
  "token": "tokn_test_xxx",
  "amount": 100,
  "currency": "THB",
  "description": "教育费用"
}
```

#### 请求示例
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

#### 响应示例
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

#### 错误响应
```json
{
  "success": false,
  "message": "支付处理失败"
}
```

### 4. 获取支付详情

#### 接口信息
- **URL**: `GET /api/payment/charge/{chargeId}`
- **认证**: 需要 Bearer Token
- **描述**: 获取支付详情

#### 请求示例
```http
GET /api/payment/charge/chrg_test_xxx
Authorization: Bearer {token}
```

#### 响应示例
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

#### 接口信息
- **URL**: `POST /api/payment/refund`
- **认证**: 需要 Bearer Token
- **描述**: 处理退款

#### 请求参数
```json
{
  "charge_id": "chrg_test_xxx",
  "amount": 50
}
```

#### 请求示例
```http
POST /api/payment/refund
Authorization: Bearer {token}
Content-Type: application/json

{
  "charge_id": "chrg_test_xxx",
  "amount": 50
}
```

#### 响应示例
```json
{
  "success": true,
  "data": {
    "refund_id": "rfnd_test_xxx"
  }
}
```

### 6. Webhook 处理

#### 接口信息
- **URL**: `POST /api/payment/webhook`
- **认证**: 无需认证（通过签名验证）
- **描述**: 处理 Omise Webhook 事件

#### 支持的 Webhook 事件
- `charge.complete`: 支付完成
- `charge.failed`: 支付失败

#### 请求示例
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

#### 响应示例
```json
{
  "status": "ok"
}
```

## 🔧 服务类实现

### OmiseService 类

#### 主要方法

##### 1. 创建令牌
```php
public function createToken(array $cardData)
{
    try {
        $token = Token::create([
            'card' => [
                'name' => $cardData['name'],
                'number' => $cardData['number'],
                'expiration_month' => $cardData['expiration_month'],
                'expiration_year' => $cardData['expiration_year'],
                'security_code' => $cardData['security_code'],
            ]
        ]);

        return [
            'success' => true,
            'token' => $token,
            'token_id' => $token['id']
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
```

##### 2. 处理支付
```php
public function processPayment(array $paymentData)
{
    try {
        $charge = Charge::create([
            'amount' => $paymentData['amount'] * 100, // 转换为分
            'currency' => $paymentData['currency'] ?? 'THB',
            'card' => $paymentData['token'],
            'description' => $paymentData['description'] ?? '教育费用',
            'capture' => true, // 立即捕获支付
        ]);

        return [
            'success' => true,
            'charge' => $charge,
            'charge_id' => $charge['id'],
            'status' => $charge['status'],
            'amount' => $charge['amount'] / 100, // 转换回元
            'currency' => $charge['currency'],
            'transaction_id' => $charge['transaction'],
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
```

##### 3. 获取支付详情
```php
public function getCharge(string $chargeId)
{
    try {
        $charge = Charge::retrieve($chargeId);
        
        return [
            'success' => true,
            'charge' => $charge
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
```

##### 4. 退款
```php
public function refund(string $chargeId, int $amount = null)
{
    try {
        $charge = Charge::retrieve($chargeId);
        $refund = $charge->refunds()->create([
            'amount' => $amount ?? $charge['amount']
        ]);

        return [
            'success' => true,
            'refund' => $refund,
            'refund_id' => $refund['id']
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
```

##### 5. 验证 Webhook 签名
```php
public function verifyWebhook(string $payload, string $signature)
{
    $expectedSignature = hash_hmac('sha256', $payload, config('omise.webhook.secret'));
    return hash_equals($expectedSignature, $signature);
}
```

## 🔐 安全措施

### 1. 认证和授权
- 支付处理接口需要 Bearer Token 认证
- Webhook 通过签名验证确保来源可信

### 2. 数据验证
- 所有输入数据都经过严格验证
- 信用卡信息验证
- 金额和货币验证

### 3. 错误处理
- 统一的错误响应格式
- 详细的错误日志记录
- 异常情况的安全处理

## 📊 日志记录

### 支付日志
```php
Log::info('支付成功', [
    'charge_id' => $result['charge_id'],
    'amount' => $result['amount'],
    'currency' => $result['currency'],
    'user_id' => $request->attributes->get('auth_user')->user_id ?? null
]);
```

### Webhook 日志
```php
Log::info('Omise Webhook 接收', $data);
Log::info('支付完成', [
    'charge_id' => $data['data']['id'],
    'amount' => $data['data']['amount']
]);
```

## 🚀 使用示例

### 前端集成示例

#### 1. 获取支付配置
```javascript
const response = await fetch('/api/payment/config');
const config = await response.json();
console.log(config.data.public_key);
```

#### 2. 创建支付令牌
```javascript
const tokenResponse = await fetch('/api/payment/create-token', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    name: 'John Doe',
    number: '4242424242424242',
    expiration_month: '12',
    expiration_year: '2025',
    security_code: '123'
  })
});
const tokenData = await tokenResponse.json();
```

#### 3. 处理支付
```javascript
const paymentResponse = await fetch('/api/payment/process', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify({
    token: tokenData.data.token_id,
    amount: 100,
    currency: 'THB',
    description: '教育费用'
  })
});
```

## 🔧 环境配置

### 开发环境
```env
OMISE_PUBLIC_KEY=pkey_test_65ggqd9jdlaax89pkex
OMISE_SECRET_KEY=skey_test_65ggqda75e2fzsxfvty
OMISE_ENVIRONMENT=test
OMISE_DEFAULT_CURRENCY=THB
OMISE_WEBHOOK_ENABLED=true
OMISE_WEBHOOK_SECRET=your_webhook_secret
```

### 生产环境
```env
OMISE_PUBLIC_KEY=pkey_live_xxx
OMISE_SECRET_KEY=skey_live_xxx
OMISE_ENVIRONMENT=live
OMISE_DEFAULT_CURRENCY=THB
OMISE_WEBHOOK_ENABLED=true
OMISE_WEBHOOK_SECRET=your_production_webhook_secret
```

## 📈 监控和维护

### 支付监控
- 支付成功率监控
- 失败支付分析
- 异常支付告警

### 性能优化
- 支付处理超时设置
- 重试机制配置
- 缓存策略优化

---

**注意**: 这是 `api-system` 中所有 Omise 支付相关接口的完整文档，包括配置、接口定义、服务实现、安全措施和使用示例。所有接口都经过 Swagger 文档化，可以通过 `/api/docs` 查看在线文档。
