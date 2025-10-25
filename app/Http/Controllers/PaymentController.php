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
     *     path="/api/payment/create-token",
     *     summary="创建支付令牌",
     *     description="创建 Omise 支付令牌",
     *     tags={"Payment"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="number", type="string", example="4242424242424242"),
     *             @OA\Property(property="expiration_month", type="string", example="12"),
     *             @OA\Property(property="expiration_year", type="string", example="2025"),
     *             @OA\Property(property="security_code", type="string", example="123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="token_id", type="string", example="tokn_test_xxx")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="请求错误"
     *     )
     * )
     */
    public function createToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'number' => 'required|string|min:13|max:19',
            'expiration_month' => 'required|string|size:2',
            'expiration_year' => 'required|string|size:2',
            'security_code' => 'required|string|min:3|max:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 400);
        }

        $result = $this->omiseService->createToken($request->all());

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'data' => [
                    'token_id' => $result['token_id']
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error']
        ], 400);
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
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'currency' => 'required|string|in:THB,USD,EUR,JPY,SGD',
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 400);
        }

        $result = $this->omiseService->processPayment($request->all());

        if ($result['success']) {
            // 记录支付日志
            Log::channel('omise')->info('支付成功', [
                'charge_id' => $result['charge_id'],
                'amount' => $result['amount'],
                'currency' => $result['currency'],
                'user_id' => $request->attributes->get('auth_user')->user_id ?? null,
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
            'data' => $data['data'] ?? null,
            'created' => $data['created'] ?? null
        ]);

        // 处理不同类型的 webhook 事件
        switch ($data['type']) {
            case 'charge.complete':
                $this->handleChargeComplete($data);
                break;
            case 'charge.failed':
                $this->handleChargeFailed($data);
                break;
            default:
                Log::info('未处理的 Webhook 事件类型: ' . $data['type']);
        }

        return response()->json(['status' => 'ok']);
    }

    private function handleChargeComplete($data)
    {
        Log::channel('omise')->info('支付完成', [
            'charge_id' => $data['data']['id'],
            'amount' => $data['data']['amount'],
            'currency' => $data['data']['currency'] ?? null,
            'status' => $data['data']['status'] ?? null,
            'transaction_id' => $data['data']['transaction'] ?? null
        ]);
    }

    private function handleChargeFailed($data)
    {
        Log::channel('omise')->warning('支付失败', [
            'charge_id' => $data['data']['id'],
            'failure_code' => $data['data']['failure_code'],
            'failure_message' => $data['data']['failure_message'],
            'amount' => $data['data']['amount'] ?? null,
            'currency' => $data['data']['currency'] ?? null
        ]);
    }
}
