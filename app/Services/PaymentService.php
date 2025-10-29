<?php

namespace App\Services;

use App\Repositories\PaymentRepository;
use App\Models\Invoice;
use App\Models\WebhookEvent;
use App\Http\Requests\Payment\ProcessPaymentRequest;
use App\Http\Requests\Payment\RefundRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PaymentService
{
    protected $paymentRepository;
    protected $omiseService;

    public function __construct(PaymentRepository $paymentRepository, \App\Services\OmiseService $omiseService)
    {
        $this->paymentRepository = $paymentRepository;
        $this->omiseService = $omiseService;
    }

    /**
     * 获取支付配置
     *
     * @return array
     */
    public function getPaymentConfig(): array
    {
        return [
            'public_key' => $this->omiseService->getPublicKey(),
            'environment' => $this->omiseService->isTestEnvironment() ? 'test' : 'live',
            'supported_currencies' => config('omise.supported_currencies'),
            'payment_methods' => config('omise.payment_methods'),
        ];
    }

    /**
     * 处理支付
     *
     * @param Request $request
     * @param int $userId
     * @return array
     * @throws \Exception
     */
    public function processPayment(ProcessPaymentRequest $request, int $userId): array
    {
        $invoice = $this->paymentRepository->findInvoice($request->invoice_id);
        if (!$invoice) {
            throw new \Exception('发票不存在');
        }

        if ((int)$invoice->status !== Invoice::STATUS_PENDING) {
            throw new \Exception('发票已在支付流程中或已完成，拒绝重复支付');
        }

        // 构建支付数据
        $paymentData = $request->all();
        $paymentData['metadata'] = [
            'invoice_id' => $request->invoice_id,
            'user_id' => $userId,
            'timestamp' => now()->toISOString(),
        ];

        $result = $this->omiseService->processPayment($paymentData);

        if ($result['success']) {
            // 更新账单支付信息
            $this->paymentRepository->updateInvoiceStatus($request->invoice_id, Invoice::STATUS_PROCESSING, [
                'omise_charge_id' => $result['charge_id'],
                'payment_success' => true,
                'payment_status' => $result['status'],
                'payment_transaction_id' => $result['transaction_id'],
                'payment_processed_at' => now(),
                'paid_at' => now(),
            ]);

            return [
                'charge_id' => $result['charge_id'],
                'status' => $result['status'],
                'amount' => $result['amount'],
                'currency' => $result['currency'],
                'transaction_id' => $result['transaction_id']
            ];
        } else {
            // 更新账单支付失败信息
            $this->paymentRepository->updateInvoiceStatus($request->invoice_id, Invoice::STATUS_FAILED, [
                'payment_success' => false,
                'payment_status' => 'failed',
                'payment_error_message' => $result['error'],
                'payment_processed_at' => now(),
            ]);

            throw new \Exception($result['error']);
        }
    }

    /**
     * 获取支付详情
     *
     * @param string $chargeId
     * @return array
     * @throws \Exception
     */
    public function getCharge(string $chargeId): array
    {
        $result = $this->omiseService->getCharge($chargeId);

        if (!$result['success']) {
            throw new \Exception($result['error']);
        }

        return $result['charge'];
    }

    /**
     * 处理退款
     *
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function processRefund(RefundRequest $request): array
    {
        $result = $this->omiseService->refund(
            $request->charge_id,
            $request->amount ? $request->amount * 100 : null
        );

        if (!$result['success']) {
            throw new \Exception($result['error']);
        }

        return [
            'refund_id' => $result['refund_id']
        ];
    }

    /**
     * 处理Webhook事件
     *
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function handleWebhook(Request $request): array
    {
        $signature = $request->header('X-Omise-Signature');
        $payload = $request->getContent();
        
        // 验证签名（测试环境可以跳过）
        $isTestMode = config('omise.environment') === 'test';
        if (!$isTestMode && !$this->omiseService->verifyWebhook($payload, $signature)) {
            throw new \Exception('Invalid signature');
        }

        $data = json_decode($payload, true);
        
        // 判断是 webhook 事件还是直接的对象
        $isWebhookEvent = isset($data['type']) && isset($data['data']);
        $eventId = $data['id'] ?? null;
        
        if ($isWebhookEvent) {
            $webhookType = $data['type'];
            $eventData = $data['data'] ?? $data;
        } else {
            $objectType = $data['data']['object'] ?? 'unknown';
            $status = $data['data']['status'] ?? null;

            if ($objectType === 'charge') {
                if ($status === 'successful') {
                    $webhookType = 'charge.complete';
                } elseif ($status === 'failed') {
                    $webhookType = 'charge.failed';
                } else {
                    $webhookType = 'charge.' . $status;
                }
            } else {
                $webhookType = $objectType . '.unknown';
            }
            
            $eventData = $data;
        }

        // 幂等性检查
        if ($eventId) {
            $existingEvent = $this->paymentRepository->findWebhookEventByEventId($eventId);
            if ($existingEvent && $existingEvent->isProcessed()) {
                return ['status' => 'ok'];
            }
        }

        // 记录 webhook 事件到数据库
        if ($eventId) {
            $webhookEvent = $this->paymentRepository->findOrCreateWebhookEvent($eventId, [
                'type' => $webhookType,
                'payload' => $data,
                'process_status' => WebhookEvent::STATUS_PENDING,
                'event_created_at' => isset($data['created']) ? Carbon::createFromTimestamp($data['created']) : null,
            ]);
        } else {
            $webhookEvent = $this->paymentRepository->createWebhookEvent([
                'event_id' => 'temp_' . uniqid(),
                'type' => $webhookType,
                'payload' => $data,
                'process_status' => WebhookEvent::STATUS_PENDING,
                'event_created_at' => isset($data['created']) ? Carbon::createFromTimestamp($data['created']) : null,
            ]);
        }

        try {
            // 处理不同类型的 webhook 事件
            switch ($webhookType) {
                case 'charge.complete':
                    $this->handleChargeComplete($eventData, $webhookEvent);
                    break;
                case 'charge.failed':
                    $this->handleChargeFailed($eventData, $webhookEvent);
                    break;
                case 'refund.created':
                    $this->handleRefundCreated($eventData, $webhookEvent);
                    break;
                default:
                    $webhookEvent->markAsProcessed();
            }

            return ['status' => 'ok'];
        } catch (\Exception $e) {
            $webhookEvent->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * 处理支付完成事件
     *
     * @param array $data
     * @param WebhookEvent $webhookEvent
     * @return void
     */
    private function handleChargeComplete(array $data, WebhookEvent $webhookEvent): void
    {
        $chargeData = isset($data['data']) ? $data['data'] : $data;
        $chargeId = $chargeData['id'];
        $metadata = $chargeData['metadata'] ?? [];

        // 更新发票状态
        if (isset($metadata['invoice_id'])) {
            $this->paymentRepository->updateInvoiceStatus($metadata['invoice_id'], Invoice::STATUS_PAID, [
                'omise_charge_id' => $chargeId,
                'transaction_id' => $chargeData['transaction'] ?? null,
                'amount' => $chargeData['amount'],
                'currency' => $chargeData['currency'],
                'paid_at' => now(),
            ]);

            // 支付成功后，将学生和课程关联写入 course_student 表
            $this->enrollStudentToCourse($metadata['invoice_id']);
        }

        $webhookEvent->markAsProcessed();
    }

    /**
     * 处理支付失败事件
     *
     * @param array $data
     * @param WebhookEvent $webhookEvent
     * @return void
     */
    private function handleChargeFailed(array $data, WebhookEvent $webhookEvent): void
    {
        $chargeData = $data['data'];
        $chargeId = $chargeData['id'];
        $metadata = $chargeData['metadata'] ?? [];

        // 更新发票状态为失败
        if (isset($metadata['invoice_id'])) {
            $this->paymentRepository->updateInvoiceStatus($metadata['invoice_id'], Invoice::STATUS_FAILED, [
                'omise_charge_id' => $chargeId,
                'failure_code' => $chargeData['failure_code'] ?? null,
                'failure_message' => $chargeData['failure_message'] ?? null,
                'failed_at' => now(),
            ]);
        }

        $webhookEvent->markAsProcessed();
    }

    /**
     * 处理退款创建事件
     *
     * @param array $data
     * @param WebhookEvent $webhookEvent
     * @return void
     */
    private function handleRefundCreated(array $data, WebhookEvent $webhookEvent): void
    {
        $refundData = $data['data'];
        $chargeId = $refundData['charge'];
        $metadata = $refundData['metadata'] ?? [];

        // 更新发票状态为已退款
        if (isset($metadata['invoice_id'])) {
            $this->paymentRepository->updateInvoiceStatus($metadata['invoice_id'], Invoice::STATUS_REFUNDED, [
                'refund_id' => $refundData['id'],
                'omise_charge_id' => $chargeId,
                'refund_amount' => $refundData['amount'],
                'currency' => $refundData['currency'],
                'refunded_at' => now(),
            ]);
        }

        $webhookEvent->markAsProcessed();
    }

    /**
     * 支付成功后，将学生和课程关联写入 course_student 表
     *
     * @param int $invoiceId
     * @return void
     */
    private function enrollStudentToCourse(int $invoiceId): void
    {
        $invoiceData = $this->paymentRepository->getInvoiceWithRelations($invoiceId);
        if (!$invoiceData) {
            return;
        }

        $this->paymentRepository->createStudentCourseEnrollment(
            $invoiceData['student_id'],
            $invoiceData['course_id']
        );
    }

}
