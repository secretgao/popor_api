<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class OmiseService
{
    protected $publicKey;
    protected $secretKey;
    protected $environment;
    protected $logger;
    
    public function __construct()
    {
        $this->publicKey = config('omise.public_key');
        $this->secretKey = config('omise.secret_key');
        $this->environment = config('omise.environment');
        $this->logger = Log::channel('omise');
        
        // 调试配置加载
        $this->logger->info('OmiseService 构造函数', [
            'public_key' => $this->publicKey,
            'secret_key' => $this->secretKey ? substr($this->secretKey, 0, 20) . '...' : 'null',
            'environment' => $this->environment
        ]);
        
        // 验证配置
        if (!$this->publicKey) {
            $this->logger->error('Omise 公钥未配置');
            throw new \Exception('Omise 公钥未配置');
        }
        
        if (!$this->secretKey) {
            $this->logger->error('Omise 私钥未配置');
            throw new \Exception('Omise 私钥未配置');
        }

        // 设置 Omise 全局常量（Omise SDK 需要）
        if (!defined('OMISE_PUBLIC_KEY')) {
            define('OMISE_PUBLIC_KEY', $this->publicKey);
        }
        if (!defined('OMISE_SECRET_KEY')) {
            define('OMISE_SECRET_KEY', $this->secretKey);
        }
    }

    /**
     * 处理支付
     *
     * @param array $paymentData 支付数据
     * @return array
     */
    public function processPayment(array $paymentData)
    {
        try {
            
            // 验证和转换货币
            $currency = strtoupper($paymentData['currency'] ?? 'THB');
            $amount = floatval($paymentData['amount']);
            
            // 验证货币是否支持
            $supportedCurrencies = ['THB', 'USD', 'EUR', 'JPY', 'SGD'];
            if (!in_array($currency, $supportedCurrencies)) {
                throw new \Exception("不支持的货币: {$currency}，支持的货币: " . implode(', ', $supportedCurrencies));
            }
            
            // 检查货币兼容性（Omise 账户可能只支持特定货币）
            $this->logger->info('货币兼容性检查', [
                'requested_currency' => $currency,
                'amount' => $amount
            ]);
            
            // 如果请求的是泰铢但账户可能不支持，尝试使用日元
            if ($currency === 'THB') {
                $this->logger->info('检测到泰铢支付请求，检查账户兼容性');
                // 这里可以添加账户货币检查逻辑
            }
            
            // 验证金额
            if ($amount <= 0) {
                throw new \Exception("支付金额必须大于0");
            }
            
            // 验证货币最小金额要求
            $minAmounts = [
                'JPY' => 100,  // 日元最小 100
                'USD' => 1,    // 美元最小 1
                'EUR' => 1,    // 欧元最小 1
                'THB' => 1,    // 泰铢最小 1
                'SGD' => 1,    // 新加坡元最小 1
            ];
            
            if (isset($minAmounts[$currency]) && $amount < $minAmounts[$currency]) {
                throw new \Exception("{$currency} 支付金额必须大于等于 {$minAmounts[$currency]}");
            }
            
            // 记录支付参数
            $this->logger->info('Omise 支付参数', [
                'amount' => $amount,
                'currency' => $currency,
                'token' => $paymentData['token'],
                'description' => $paymentData['description'] ?? '教育费用'
            ]);
            
            // 根据货币类型处理金额
            $omiseAmount = $amount;
            if ($currency === 'USD' || $currency === 'EUR' || $currency === 'SGD' || $currency === 'THB') {
                // 美元、欧元、新加坡元、泰铢需要转换为最小单位（乘以100）
                $omiseAmount = intval($amount * 100);
            } else {
                // 日元等直接使用原金额
                $omiseAmount = intval($amount);
            }
            
            $chargeParams = [
                'amount' => $omiseAmount,
                'currency' => $currency,
                'card' => $paymentData['token'],
                'description' => $paymentData['description'] ?? '教育费用',
                'capture' => true, // 立即捕获支付
            ];

            // 添加 metadata 支持
            if (isset($paymentData['metadata']) && is_array($paymentData['metadata'])) {
                $chargeParams['metadata'] = $paymentData['metadata'];
            }

            $this->logger->info('发送给 Omise 的参数', [
                'original_amount' => $amount,
                'currency' => $currency,
                'omise_amount' => $omiseAmount,
                'amount_conversion' => $currency === 'USD' || $currency === 'EUR' || $currency === 'SGD' || $currency === 'THB' ? 'multiplied by 100' : 'no conversion',
                'full_params' => $chargeParams
            ]);
            
            try {
                $charge = \OmiseCharge::create($chargeParams);
            } catch (\Exception $e) {
                // 如果是货币转换错误且请求的是泰铢，尝试使用日元
                if (strpos($e->getMessage(), 'currency conversion') !== false && $currency === 'THB') {
                    $this->logger->info('泰铢支付失败，尝试使用日元', [
                        'original_currency' => $currency,
                        'fallback_currency' => 'JPY',
                        'original_amount' => $amount,
                        'fallback_amount' => $amount * 100 // 1 泰铢 ≈ 100 日元
                    ]);
                    
                    // 使用日元重新尝试
                    $fallbackParams = $chargeParams;
                    $fallbackParams['currency'] = 'JPY';
                    $fallbackParams['amount'] = intval($amount * 100); // 转换为日元
                    
                    $charge = \OmiseCharge::create($fallbackParams);
                    
                    $this->logger->info('日元支付成功', [
                        'charge_id' => $charge['id'],
                        'currency' => 'JPY',
                        'amount' => $charge['amount']
                    ]);
                } else {
                    throw $e;
                }
            }
            
            $this->logger->info('Omise 响应', [
                'charge_id' => $charge['id'],
                'status' => $charge['status'],
                'amount' => $charge['amount'],
                'currency' => $charge['currency']
            ]);

            // 根据货币类型转换回显示金额
            $displayAmount = $charge['amount'];
            if ($currency === 'USD' || $currency === 'EUR' || $currency === 'SGD' || $currency === 'THB') {
                $displayAmount = $charge['amount'] / 100; // 美元、欧元、新加坡元、泰铢需要除以100
            }
            
            $this->logger->info('Omise 支付处理成功', [
                'charge_id' => $charge['id'],
                'status' => $charge['status'],
                'amount' => $displayAmount,
                'currency' => $charge['currency'],
                'transaction_id' => $charge['transaction'],
                'description' => $paymentData['description'] ?? null,
                'metadata' => $paymentData['metadata'] ?? null,
            ]);

            return [
                'success' => true,
                'charge' => $charge,
                'charge_id' => $charge['id'],
                'status' => $charge['status'],
                'amount' => $displayAmount,
                'currency' => $charge['currency'],
                'transaction_id' => $charge['transaction'],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Omise 支付处理失败', [
                'message' => $e->getMessage(),
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'payment_data' => [
                    'amount' => $paymentData['amount'] ?? null,
                    'currency' => $paymentData['currency'] ?? null,
                    'description' => $paymentData['description'] ?? null,
                    'token' => $paymentData['token'] ?? null,
                    'metadata' => $paymentData['metadata'] ?? null,
                ]
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 创建客户
     *
     * @param array $customerData 客户数据
     * @return array
     */
    public function createCustomer(array $customerData)
    {
        try {
            $customer = \OmiseCustomer::create([
                'email' => $customerData['email'],
                'description' => $customerData['description'] ?? '教育系统客户',
                'metadata' => $customerData['metadata'] ?? []
            ]);

            return [
                'success' => true,
                'customer' => $customer,
                'customer_id' => $customer['id']
            ];
        } catch (\Exception $e) {
            $this->logger->error('Omise 创建客户失败', [
                'message' => $e->getMessage(),
                'error' => $e->getMessage(),
                'customer_data' => [
                    'email' => $customerData['email'] ?? null,
                    'description' => $customerData['description'] ?? null,
                ]
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 获取支付详情
     *
     * @param string $chargeId 支付ID
     * @return array
     */
    public function getCharge(string $chargeId)
    {
        try {
            $charge = \OmiseCharge::retrieve($chargeId);
            
            return [
                'success' => true,
                'charge' => $charge
            ];
        } catch (\Exception $e) {
            $this->logger->error('Omise 获取支付详情失败', [
                'message' => $e->getMessage(),
                'error' => $e->getMessage(),
                'charge_id' => $chargeId
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 退款
     *
     * @param string $chargeId 支付ID
     * @param int $amount 退款金额（分）
     * @return array
     */
    public function refund(string $chargeId, int $amount = null)
    {
        try {
            $charge = \OmiseCharge::retrieve($chargeId);
            $refund = $charge->refunds()->create([
                'amount' => $amount ?? $charge['amount']
            ]);

            $this->logger->info('Omise 退款成功', [
                'refund_id' => $refund['id'],
                'charge_id' => $chargeId,
                'refund_amount' => $amount ?? $charge['amount'],
                'original_amount' => $charge['amount'],
            ]);

            return [
                'success' => true,
                'refund' => $refund,
                'refund_id' => $refund['id']
            ];
        } catch (\Exception $e) {
            $this->logger->error('Omise 退款失败', [
                'message' => $e->getMessage(),
                'error' => $e->getMessage(),
                'charge_id' => $chargeId,
                'refund_amount' => $amount
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 验证 Webhook 签名
     *
     * @param string $payload 请求体
     * @param string|null $signature 签名
     * @return bool
     */
    public function verifyWebhook(string $payload, ?string $signature)
    {
        if (empty($signature)) {
            Log::channel('omise')->warning('Webhook 签名为空', [
                'signature' => $signature,
                'payload_length' => strlen($payload)
            ]);
            return true;
        }
        
        $webhookSecret = config('omise.webhook.secret');
        $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
        
        Log::channel('omise')->info('Webhook 签名验证详情', [
            'received_signature' => $signature,
            'expected_signature' => $expectedSignature,
            'webhook_secret_length' => strlen($webhookSecret),
            'payload_length' => strlen($payload)
        ]);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * 获取公钥（前端使用）
     *
     * @return string
     */
    public function getPublicKey()
    {
        return $this->publicKey;
    }

    /**
     * 检查环境
     *
     * @return bool
     */
    public function isTestEnvironment()
    {
        return $this->environment === 'test';
    }
}
