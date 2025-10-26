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
       
    }

    
    /**
     * 创建支付令牌
     *
     * @param array $cardData 卡片数据
     * @return array
     */
    public function createToken(array $cardData)
    {
        try {
            $token = \OmiseToken::create([
                'card' => [
                    'name' => $cardData['name'],
                    'number' => $cardData['number'],
                    'expiration_month' => $cardData['expiration_month'],
                    'expiration_year' => $cardData['expiration_year'],
                    'security_code' => $cardData['security_code'],
                ]
            ]);

            $this->logger->info('Omise 令牌创建成功', [
                'token_id' => $token['id'],
                'card_brand' => $token['card']['brand'] ?? null,
                'card_last_digits' => $token['card']['last_digits'] ?? null,
            ]);

            return [
                'success' => true,
                'token' => $token,
                'token_id' => $token['id']
            ];
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
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
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
            $chargeParams = [
                'amount' => $paymentData['amount'] * 100, // 转换为分
                'currency' => $paymentData['currency'] ?? 'THB',
                'card' => $paymentData['token'],
                'description' => $paymentData['description'] ?? '教育费用',
                'capture' => true, // 立即捕获支付
            ];

            // 添加 metadata 支持
            if (isset($paymentData['metadata']) && is_array($paymentData['metadata'])) {
                $chargeParams['metadata'] = $paymentData['metadata'];
            }

            $charge = \OmiseCharge::create($chargeParams);

            $this->logger->info('Omise 支付处理成功', [
                'charge_id' => $charge['id'],
                'status' => $charge['status'],
                'amount' => $charge['amount'] / 100,
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
                'amount' => $charge['amount'] / 100, // 转换回元
                'currency' => $charge['currency'],
                'transaction_id' => $charge['transaction'],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Omise 支付处理失败', [
                'error' => $e->getMessage(),
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
                'error' => $e->getMessage(),
                'customer_data' => [
                    'email' => $customerData['email'] ?? null,
                    'description' => $customerData['description'] ?? null,
                ]
            ]);
            return [
                'success' => false,
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
                'error' => $e->getMessage(),
                'charge_id' => $chargeId
            ]);
            return [
                'success' => false,
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
                'error' => $e->getMessage(),
                'charge_id' => $chargeId,
                'refund_amount' => $amount
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 验证 Webhook 签名
     *
     * @param string $payload 请求体
     * @param string $signature 签名
     * @return bool
     */
    public function verifyWebhook(string $payload, string $signature)
    {
        $expectedSignature = hash_hmac('sha256', $payload, config('omise.webhook.secret'));
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
