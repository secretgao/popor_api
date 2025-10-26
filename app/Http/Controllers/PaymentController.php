<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\OmiseService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Payment",
 *     description="支付相关接口"
 * )
 */
class PaymentController extends Controller
{
    protected $omiseService;

    public function __construct(OmiseService $omiseService)
    {
        $this->omiseService = $omiseService;
    }

    /**
     * @OA\Get(
     *     path="/api/payment/config",
     *     summary="获取支付配置",
     *     description="获取前端支付配置信息",
     *     tags={"Payment"},
     *     @OA\Response(
     *         response=200,
     *         description="成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="public_key", type="string", example="pkey_test_65ggqd9jdlaax89pkex"),
     *                 @OA\Property(property="environment", type="string", example="test"),
     *                 @OA\Property(property="supported_currencies", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function getConfig()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'public_key' => $this->omiseService->getPublicKey(),
                'environment' => $this->omiseService->isTestEnvironment() ? 'test' : 'live',
                'supported_currencies' => config('omise.supported_currencies'),
                'payment_methods' => config('omise.payment_methods'),
            ]
        ]);
    }


    /**
     * @OA\Post(
     *     path="/api/payment/process",
     *     summary="处理支付",
     *     description="使用 Omise 处理支付",
     *     tags={"Payment"},
     *     security={{"bearer_token":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string", example="tokn_test_xxx"),
     *             @OA\Property(property="amount", type="number", example=100),
     *             @OA\Property(property="currency", type="string", example="THB"),
     *             @OA\Property(property="description", type="string", example="教育费用")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="charge_id", type="string", example="chrg_test_xxx"),
     *                 @OA\Property(property="status", type="string", example="successful"),
     *                 @OA\Property(property="amount", type="number", example=100),
     *                 @OA\Property(property="currency", type="string", example="THB")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="请求错误"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="未授权"
     *     )
     * )
     */
    public function processPayment(Request $request)
    {
        Log::channel('omise')->info('processPayment-原始参数', [
            'raw_data' => $request->all()
        ]);
        
        // 处理可能被包装在 data 对象中的数据
        $requestData = $request->all();
        if (isset($requestData['data']) && is_array($requestData['data'])) {
            $requestData = $requestData['data'];
            Log::channel('omise')->info('processPayment-解包数据', [
                'unpacked_data' => $requestData
            ]);
        }
        
        Log::channel('omise')->info('processPayment-最终参数', [
            'final_data' => $requestData
        ]);
        
        $validator = Validator::make($requestData, [
            'token' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'currency' => 'required|string|in:THB,USD,EUR,JPY,SGD',
            'description' => 'nullable|string|max:255',
            'invoice_id' => 'required|int|max:255', // 添加发票ID支持
        ]);

        if ($validator->fails()) {
            Log::channel('omise')->error('processPayment-验证失败', [
                'errors' => $validator->errors()
            ]);
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 400);
        }

        // 构建支付数据，包含 metadata
        $paymentData = $requestData;
        $paymentData['metadata'] = [
            'invoice_id' => $requestData['invoice_id'] ?? null,
            'user_id' => $request->attributes->get('auth_user')->user_id ?? null,
            'timestamp' => now()->toISOString(),
        ];

        $result = $this->omiseService->processPayment($paymentData);

        if ($result['success']) {
            // 记录支付日志
            Log::channel('omise')->info('支付成功', [
                'charge_id' => $result['charge_id'],
                'amount' => $result['amount'],
                'currency' => $result['currency'],
                'user_id' => $request->attributes->get('auth_user')->user_id ?? null,
                'invoice_id' => $requestData['invoice_id'] ?? null,
                'transaction_id' => $result['transaction_id']
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'charge_id' => $result['charge_id'],
                    'status' => $result['status'],
                    'amount' => $result['amount'],
                    'currency' => $result['currency'],
                    'transaction_id' => $result['transaction_id']
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error']
        ], 400);
    }

    /**
     * @OA\Get(
     *     path="/api/payment/charge/{chargeId}",
     *     summary="获取支付详情",
     *     description="获取支付详情",
     *     tags={"Payment"},
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="chargeId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="支付ID"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="支付不存在"
     *     )
     * )
     */
    public function getCharge(string $chargeId)
    {
        $result = $this->omiseService->getCharge($chargeId);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'data' => $result['charge']
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error']
        ], 404);
    }

    /**
     * @OA\Post(
     *     path="/api/payment/refund",
     *     summary="退款",
     *     description="处理退款",
     *     tags={"Payment"},
     *     security={{"bearer_token":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="charge_id", type="string", example="chrg_test_xxx"),
     *             @OA\Property(property="amount", type="number", example=50, description="退款金额，不填则全额退款")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="请求错误"
     *     )
     * )
     */
    public function refund(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'charge_id' => 'required|string',
            'amount' => 'nullable|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 400);
        }

        $result = $this->omiseService->refund(
            $request->charge_id,
            $request->amount ? $request->amount * 100 : null
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'data' => [
                    'refund_id' => $result['refund_id']
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error']
        ], 400);
    }

    /**
     * Webhook 处理
     */
    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Omise-Signature');

        // 验证签名
        if (!$this->omiseService->verifyWebhook($payload, $signature)) {
            Log::channel('omise')->warning('Omise Webhook 签名验证失败', [
                'signature' => $signature,
                'payload_length' => strlen($payload)
            ]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $data = json_decode($payload, true);
        
        Log::channel('omise')->info('Omise Webhook 接收', [
            'type' => $data['type'] ?? null,
            'event_id' => $data['id'] ?? null,
            'data' => $data['data'] ?? null,
            'created' => $data['created'] ?? null
        ]);

        // 幂等性检查：先检查事件是否已处理
        $eventId = $data['id'] ?? null;
        if ($eventId) {
            $existingEvent = \App\Models\WebhookEvent::where('event_id', $eventId)->first();
            if ($existingEvent && $existingEvent->isProcessed()) {
                Log::channel('omise')->info('Webhook 事件已处理，跳过', [
                    'event_id' => $eventId,
                    'type' => $data['type']
                ]);
                return response()->json(['status' => 'ok']);
            }
        }

        // 记录 webhook 事件到数据库
        $webhookEvent = \App\Models\WebhookEvent::findOrCreateByEventId($eventId, [
            'type' => $data['type'] ?? null,
            'payload' => $data,
            'process_status' => \App\Models\WebhookEvent::STATUS_PENDING,
            'event_created_at' => isset($data['created']) ? \Carbon\Carbon::createFromTimestamp($data['created']) : null,
        ]);

        try {
            // 处理不同类型的 webhook 事件
            switch ($data['type']) {
                case 'charge.complete':
                    $this->handleChargeComplete($data, $webhookEvent);
                    break;
                case 'charge.failed':
                    $this->handleChargeFailed($data, $webhookEvent);
                    break;
                case 'refund.created':
                    $this->handleRefundCreated($data, $webhookEvent);
                    break;
                default:
                    Log::channel('omise')->info('未处理的 Webhook 事件类型: ' . $data['type']);
                    $webhookEvent->markAsProcessed(); // 标记为已处理，避免重复处理
            }

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::channel('omise')->error('Webhook 处理失败', [
                'event_id' => $eventId,
                'type' => $data['type'],
                'error' => $e->getMessage()
            ]);
            
            $webhookEvent->markAsFailed($e->getMessage());
            return response()->json(['status' => 'ok']); // 仍然返回 200，避免 Omise 重试
        }
    }

    private function handleChargeComplete($data, $webhookEvent)
    {
        $chargeData = $data['data'];
        $chargeId = $chargeData['id'];
        $metadata = $chargeData['metadata'] ?? [];
        
        Log::channel('omise')->info('支付完成', [
            'charge_id' => $chargeId,
            'amount' => $chargeData['amount'],
            'currency' => $chargeData['currency'] ?? null,
            'status' => $chargeData['status'] ?? null,
            'transaction_id' => $chargeData['transaction'] ?? null,
            'metadata' => $metadata
        ]);

        // 更新发票状态（如果有 invoice_id）
        if (isset($metadata['invoice_id'])) {
            $this->updateInvoiceStatus($metadata['invoice_id'], 'paid', [
                'charge_id' => $chargeId,
                'transaction_id' => $chargeData['transaction'] ?? null,
                'amount' => $chargeData['amount'] / 100, // 转换回元
                'currency' => $chargeData['currency'],
                'paid_at' => now(),
            ]);
        }

        $webhookEvent->markAsProcessed();
    }

    private function handleChargeFailed($data, $webhookEvent)
    {
        $chargeData = $data['data'];
        $chargeId = $chargeData['id'];
        $metadata = $chargeData['metadata'] ?? [];
        
        Log::channel('omise')->warning('支付失败', [
            'charge_id' => $chargeId,
            'failure_code' => $chargeData['failure_code'] ?? null,
            'failure_message' => $chargeData['failure_message'] ?? null,
            'amount' => $chargeData['amount'] ?? null,
            'currency' => $chargeData['currency'] ?? null,
            'metadata' => $metadata
        ]);

        // 更新发票状态为失败（如果有 invoice_id）
        if (isset($metadata['invoice_id'])) {
            $this->updateInvoiceStatus($metadata['invoice_id'], 'failed', [
                'charge_id' => $chargeId,
                'failure_code' => $chargeData['failure_code'] ?? null,
                'failure_message' => $chargeData['failure_message'] ?? null,
                'failed_at' => now(),
            ]);
        }

        $webhookEvent->markAsProcessed();
    }

    private function handleRefundCreated($data, $webhookEvent)
    {
        $refundData = $data['data'];
        $chargeId = $refundData['charge'];
        $metadata = $refundData['metadata'] ?? [];
        
        Log::channel('omise')->info('退款创建', [
            'refund_id' => $refundData['id'],
            'charge_id' => $chargeId,
            'amount' => $refundData['amount'],
            'currency' => $refundData['currency'],
            'metadata' => $metadata
        ]);

        // 更新发票状态为已退款（如果有 invoice_id）
        if (isset($metadata['invoice_id'])) {
            $this->updateInvoiceStatus($metadata['invoice_id'], 'refunded', [
                'refund_id' => $refundData['id'],
                'charge_id' => $chargeId,
                'refund_amount' => $refundData['amount'] / 100, // 转换回元
                'currency' => $refundData['currency'],
                'refunded_at' => now(),
            ]);
        }

        $webhookEvent->markAsProcessed();
    }

    /**
     * 更新发票状态
     */
    private function updateInvoiceStatus($invoiceId, $status, $additionalData = [])
    {
        try {
            // 这里需要根据您的发票模型来更新状态
            // 假设您有一个 Invoice 模型
            $invoice = \App\Models\Invoice::find($invoiceId);
            if ($invoice) {
                $invoice->update([
                    'status' => $status,
                    'payment_data' => array_merge($invoice->payment_data ?? [], $additionalData),
                    'updated_at' => now(),
                ]);

                Log::channel('omise')->info('发票状态已更新', [
                    'invoice_id' => $invoiceId,
                    'status' => $status,
                    'additional_data' => $additionalData
                ]);
            } else {
                Log::channel('omise')->warning('未找到发票', [
                    'invoice_id' => $invoiceId,
                    'status' => $status
                ]);
            }
        } catch (\Exception $e) {
            Log::channel('omise')->error('更新发票状态失败', [
                'invoice_id' => $invoiceId,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
