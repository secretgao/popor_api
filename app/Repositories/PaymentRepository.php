<?php

namespace App\Repositories;

use App\Models\Invoice;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;

class PaymentRepository extends BaseRepository
{
    public function __construct(Invoice $model)
    {
        parent::__construct($model);
    }

    /**
     * 根据ID查找发票
     *
     * @param int $id
     * @return Invoice|null
     */
    public function findInvoice(int $id): ?Invoice
    {
        return $this->find($id);
    }

    /**
     * 更新发票状态
     *
     * @param int $invoiceId
     * @param int $status
     * @param array $additionalData
     * @return bool
     */
    public function updateInvoiceStatus(int $invoiceId, int $status, array $additionalData = []): bool
    {
        $invoice = $this->find($invoiceId);
        if (!$invoice) {
            return false;
        }

        $updateData = [
            'status' => $status,
            'updated_at' => now(),
        ];
        
        // 合并额外数据
        $updateData = array_merge($updateData, $additionalData);
        
        return $invoice->update($updateData);
    }

    /**
     * 创建学生课程关联
     *
     * @param int $studentId
     * @param int $courseId
     * @return bool
     */
    public function createStudentCourseEnrollment(int $studentId, int $courseId): bool
    {
        // 检查是否已经存在关联
        $existingEnrollment = DB::table('course_student')
            ->where('student_id', $studentId)
            ->where('course_id', $courseId)
            ->first();

        if ($existingEnrollment) {
            return true; // 已存在，返回成功
        }

        // 插入新的学生课程关联
        return DB::table('course_student')->insert([
            'student_id' => $studentId,
            'course_id' => $courseId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * 根据事件ID查找Webhook事件
     *
     * @param string $eventId
     * @return WebhookEvent|null
     */
    public function findWebhookEventByEventId(string $eventId): ?WebhookEvent
    {
        return WebhookEvent::where('event_id', $eventId)->first();
    }

    /**
     * 创建Webhook事件
     *
     * @param array $data
     * @return WebhookEvent
     */
    public function createWebhookEvent(array $data): WebhookEvent
    {
        return WebhookEvent::create($data);
    }

    /**
     * 根据事件ID查找或创建Webhook事件
     *
     * @param string $eventId
     * @param array $data
     * @return WebhookEvent
     */
    public function findOrCreateWebhookEvent(string $eventId, array $data): WebhookEvent
    {
        return WebhookEvent::findOrCreateByEventId($eventId, $data);
    }

    /**
     * 获取发票的关联数据
     *
     * @param int $invoiceId
     * @return array|null
     */
    public function getInvoiceWithRelations(int $invoiceId): ?array
    {
        $invoice = $this->find($invoiceId);
        if (!$invoice) {
            return null;
        }

        return [
            'student_id' => $invoice->student_id,
            'course_id' => $invoice->course_id,
            'status' => $invoice->status
        ];
    }
}
