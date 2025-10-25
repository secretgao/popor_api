# Omise 支付日志配置

## 📋 概述

本文档说明了 `api-system` 中 Omise 支付相关的日志配置和使用方法。

## 🔧 日志配置

### 配置文件 (`config/logging.php`)

```php
'omise' => [
    'driver' => 'daily',
    'path' => storage_path('logs/omise.log'),
    'level' => env('LOG_LEVEL', 'debug'),
    'days' => env('LOG_DAILY_DAYS', 30),
    'replace_placeholders' => true,
],
```

### 配置说明

- **驱动**: `daily` - 按天分割日志文件
- **路径**: `storage/logs/omise.log` - 日志文件路径
- **级别**: `debug` - 记录所有级别的日志
- **保留天数**: `30` - 日志文件保留30天
- **占位符替换**: `true` - 支持占位符替换

## 📊 日志记录内容

### 1. 令牌创建日志

#### 成功日志
```json
{
  "level": "info",
  "message": "Omise 令牌创建成功",
  "context": {
    "token_id": "tokn_test_xxx",
    "card_brand": "Visa",
    "card_last_digits": "4242"
  }
}
```

#### 失败日志
```json
{
  "level": "error",
  "message": "Omise 创建令牌失败",
  "context": {
    "error": "Invalid card number",
    "card_data": {
      "name": "John Doe",
      "number": "4242****4242",
      "expiration_month": "12",
      "expiration_year": "2025"
    }
  }
}
```

### 2. 支付处理日志

#### 成功日志
```json
{
  "level": "info",
  "message": "Omise 支付处理成功",
  "context": {
    "charge_id": "chrg_test_xxx",
    "status": "successful",
    "amount": 100,
    "currency": "THB",
    "transaction_id": "trxn_test_xxx",
    "description": "教育费用"
  }
}
```

#### 失败日志
```json
{
  "level": "error",
  "message": "Omise 支付处理失败",
  "context": {
    "error": "Insufficient funds",
    "payment_data": {
      "amount": 100,
      "currency": "THB",
      "description": "教育费用",
      "token": "tokn_test_xxx"
    }
  }
}
```

### 3. 退款日志

#### 成功日志
```json
{
  "level": "info",
  "message": "Omise 退款成功",
  "context": {
    "refund_id": "rfnd_test_xxx",
    "charge_id": "chrg_test_xxx",
    "refund_amount": 50,
    "original_amount": 100
  }
}
```

#### 失败日志
```json
{
  "level": "error",
  "message": "Omise 退款失败",
  "context": {
    "error": "Refund amount exceeds charge amount",
    "charge_id": "chrg_test_xxx",
    "refund_amount": 150
  }
}
```

### 4. Webhook 日志

#### Webhook 接收日志
```json
{
  "level": "info",
  "message": "Omise Webhook 接收",
  "context": {
    "type": "charge.complete",
    "data": {
      "id": "chrg_test_xxx",
      "status": "successful",
      "amount": 10000
    },
    "created": "2024-01-16T10:30:00Z"
  }
}
```

#### 支付完成日志
```json
{
  "level": "info",
  "message": "支付完成",
  "context": {
    "charge_id": "chrg_test_xxx",
    "amount": 10000,
    "currency": "THB",
    "status": "successful",
    "transaction_id": "trxn_test_xxx"
  }
}
```

#### 支付失败日志
```json
{
  "level": "warning",
  "message": "支付失败",
  "context": {
    "charge_id": "chrg_test_xxx",
    "failure_code": "insufficient_funds",
    "failure_message": "Insufficient funds",
    "amount": 10000,
    "currency": "THB"
  }
}
```

## 🔧 代码实现

### OmiseService 类

```php
class OmiseService
{
    protected $logger;

    public function __construct()
    {
        $this->logger = Log::channel('omise');
    }

    public function createToken(array $cardData)
    {
        try {
            // ... 创建令牌逻辑
            
            $this->logger->info('Omise 令牌创建成功', [
                'token_id' => $token['id'],
                'card_brand' => $token['card']['brand'] ?? null,
                'card_last_digits' => $token['card']['last_digits'] ?? null,
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Omise 创建令牌失败', [
                'error' => $e->getMessage(),
                'card_data' => [
                    'name' => $cardData['name'] ?? null,
                    'number' => substr($cardData['number'] ?? '', 0, 4) . '****' . substr($cardData['number'] ?? '', -4),
                    'expiration_month' => $cardData['expiration_month'] ?? null,
                    'expiration_year' => $cardData['expiration_year'] ?? null,
                ]
            ]);
        }
    }
}
```

### PaymentController 类

```php
class PaymentController extends Controller
{
    public function processPayment(Request $request)
    {
        $result = $this->omiseService->processPayment($request->all());

        if ($result['success']) {
            Log::channel('omise')->info('支付成功', [
                'charge_id' => $result['charge_id'],
                'amount' => $result['amount'],
                'currency' => $result['currency'],
                'user_id' => $request->attributes->get('auth_user')->user_id ?? null,
                'transaction_id' => $result['transaction_id']
            ]);
        }
    }
}
```

## 📁 日志文件结构

### 日志文件位置
```
storage/logs/
├── omise-2024-01-16.log    # 当天的 Omise 日志
├── omise-2024-01-15.log    # 前一天的日志
├── omise-2024-01-14.log    # 更早的日志
└── ...
```

### 日志文件命名规则
- 格式: `omise-YYYY-MM-DD.log`
- 自动按天分割
- 自动清理过期日志（默认保留30天）

## 🔍 日志查看

### 1. 查看当天日志
```bash
tail -f storage/logs/omise-$(date +%Y-%m-%d).log
```

### 2. 查看特定时间段的日志
```bash
grep "2024-01-16 10:" storage/logs/omise-2024-01-16.log
```

### 3. 查看错误日志
```bash
grep "error" storage/logs/omise-2024-01-16.log
```

### 4. 查看支付成功日志
```bash
grep "支付成功" storage/logs/omise-2024-01-16.log
```

## 📊 日志分析

### 1. 支付成功率统计
```bash
# 统计支付成功次数
grep -c "支付成功" storage/logs/omise-*.log

# 统计支付失败次数
grep -c "支付失败" storage/logs/omise-*.log
```

### 2. 错误类型分析
```bash
# 查看所有错误
grep "error" storage/logs/omise-*.log | jq '.context.error' | sort | uniq -c
```

### 3. 支付金额统计
```bash
# 提取支付金额
grep "支付成功" storage/logs/omise-*.log | jq '.context.amount' | awk '{sum+=$1} END {print "总金额:", sum}'
```

## 🔧 环境配置

### 开发环境
```env
LOG_LEVEL=debug
LOG_DAILY_DAYS=7
```

### 生产环境
```env
LOG_LEVEL=info
LOG_DAILY_DAYS=30
```

## 📈 监控和告警

### 1. 错误率监控
- 监控错误日志数量
- 设置错误率阈值告警
- 异常支付模式检测

### 2. 性能监控
- 支付处理时间统计
- 响应时间分析
- 吞吐量监控

### 3. 业务监控
- 支付成功率监控
- 退款率统计
- 异常交易检测

## 🔒 安全考虑

### 1. 敏感信息保护
- 信用卡号脱敏处理
- 不记录完整卡号
- 只记录必要信息

### 2. 日志访问控制
- 限制日志文件访问权限
- 定期清理敏感日志
- 加密存储重要日志

### 3. 审计要求
- 完整的操作记录
- 不可篡改的日志
- 长期保存要求

---

**注意**: 这是 Omise 支付系统的完整日志配置和使用指南，包含日志配置、记录内容、查看方法和分析技巧。所有 Omise 相关的操作都会记录到专门的日志文件中，便于监控和问题排查。
