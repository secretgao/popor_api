<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use App\Http\Requests\Payment\ProcessPaymentRequest;
use App\Http\Requests\Payment\RefundRequest;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Payment",
 *     description="支付相关接口"
 * )
 */
class PaymentController extends BaseController
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
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
        try {
            $result = $this->paymentService->getPaymentConfig();

            return $this->success($result, '获取支付配置成功');
        } catch (\Exception $e) {
            return $this->handleServiceException($e);
        }
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
    public function processPayment(ProcessPaymentRequest $request)
    {
        try {
            $user = $this->requireAuthUser($request);
            if ($user instanceof \Illuminate\Http\JsonResponse) {
                return $user;
            }

            $result = $this->paymentService->processPayment($request, $user->user_id);

            return $this->success($result, '支付处理成功');
        } catch (\Exception $e) {
            return $this->handleServiceException($e);
        }
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
        try {
            $result = $this->paymentService->getCharge($chargeId);

            return $this->success($result, '获取支付详情成功');
        } catch (\Exception $e) {
            return $this->handleServiceException($e);
        }
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
    public function refund(RefundRequest $request)
    {
        try {
            $result = $this->paymentService->processRefund($request);

            return $this->success($result, '退款处理成功');
        } catch (\Exception $e) {
            return $this->handleServiceException($e);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/payment/webhook",
     *     tags={"Payment"},
     *     summary="Omise Webhook 处理",
     *     description="处理 Omise 支付网关的 webhook 事件",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 description="Omise webhook 事件数据"
     *             )
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="X-Omise-Signature",
     *         in="header",
     *         description="Omise 签名",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="处理成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="签名验证失败",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Invalid signature")
     *         )
     *     )
     * )
     */
    public function webhook(Request $request)
    {
        try {
            $result = $this->paymentService->handleWebhook($request);

            return response()->json($result);
        } catch (\Exception $e) {
            \Log::channel('omise')->error('Webhook 处理失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['status' => 'ok']); // 仍然返回 200，避免 Omise 重试
        }
    }

}
