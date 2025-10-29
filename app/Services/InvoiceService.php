<?php

namespace App\Services;

use App\Repositories\InvoiceRepository;
use App\Models\Course;
use App\Http\Requests\Invoice\CreateInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceStatusRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InvoiceService
{
    protected $invoiceRepository;

    public function __construct(InvoiceRepository $invoiceRepository)
    {
        $this->invoiceRepository = $invoiceRepository;
    }
    /**
     * 获取账单列表
     *
     * @param Request $request
     * @param object $user
     * @return array
     */
    public function getInvoices(Request $request, object $user): array
    {
        $perPage = $request->get('per_page', 10);
        $status = $request->get('status');
        $studentId = $request->get('student_id');

        $invoices = $this->invoiceRepository->getInvoicesWithPagination(
            $perPage,
            $user->role,
            $user->user_id,
            $status,
            $studentId
        );

        // 记录SQL查询日志
        $this->logQuery('账单列表查询', null, $user, $invoices);

        // 格式化数据
        $formattedInvoices = $invoices->getCollection()->map(function($invoice) {
            return $this->formatInvoiceData($invoice);
        });

        return [
            'invoices' => $formattedInvoices->toArray(),
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
                'last_page' => $invoices->lastPage()
            ]
        ];
    }

    /**
     * 创建账单
     *
     * @param Request $request
     * @param object $user
     * @return array
     * @throws \Exception
     */
    public function createInvoice(CreateInvoiceRequest $request, object $user): array
    {
        $course = Course::find($request->course_id);
        if (!$course) {
            throw new \Exception('课程不存在');
        }

        // 检查是否已存在相同的账单
        if ($this->invoiceRepository->existsByStudentAndCourse($request->student_id, $request->course_id)) {
            throw new \Exception('该学生在此课程的账单已存在，每个学生每个课程只能有一个账单');
        }

        // 创建账单
        $invoice = $this->invoiceRepository->create([
            'student_id' => $request->student_id,
            'course_id' => $request->course_id,
            'teacher_id' => $user->user_id,
            'amount' => $request->amount,
            'year_month' => $request->year_month,
            'status' => Invoice::STATUS_DRAFT,
            'description' => $request->description,
            'currency' => 'JPY'
        ]);

        // 加载关联数据
        $invoice->load(['student', 'course', 'teacher']);

        return ['invoice' => $this->formatInvoiceData($invoice)];
    }

    /**
     * 获取账单详情
     *
     * @param int $id
     * @param object $user
     * @return array
     * @throws \Exception
     */
    public function getInvoice(int $id, object $user): array
    {
        if (!$this->invoiceRepository->existsByUser($id, $user->role, $user->user_id)) {
            throw new \Exception('账单不存在或您没有权限查看');
        }

        $invoice = $this->invoiceRepository->find($id);
        $invoice->load(['student', 'course', 'teacher']);

        return ['invoice' => $this->formatInvoiceData($invoice)];
    }

    /**
     * 更新账单
     *
     * @param Request $request
     * @param int $id
     * @param object $user
     * @return array
     * @throws \Exception
     */
    public function updateInvoice(UpdateInvoiceRequest $request, int $id, object $user): array
    {
        if (!$this->invoiceRepository->existsByUser($id, $user->role, $user->user_id)) {
            throw new \Exception('账单不存在或您没有权限修改');
        }

        $invoice = $this->invoiceRepository->find($id);
        if ($invoice->status === Invoice::STATUS_PROCESSING) {
            throw new \Exception('支付中的账单无法修改');
        }

        // 更新账单
        $this->invoiceRepository->update($id, [
            'amount' => $request->amount,
            'year_month' => $request->year_month
        ]);

        return ['message' => '账单更新成功'];
    }

    /**
     * 更新账单状态
     *
     * @param Request $request
     * @param int $id
     * @param object $user
     * @return array
     * @throws \Exception
     */
    public function updateInvoiceStatus(UpdateInvoiceStatusRequest $request, int $id, object $user): array
    {
        $invoice = $this->invoiceRepository->findOrFail($id);

        // 权限检查
        if ($user->role === 'teacher' && $invoice->teacher_id !== $user->user_id) {
            throw new \Exception('权限不足，只能更新自己创建的账单');
        }

        // 验证状态值
        $newStatus = $request->input('status');
        if (!in_array($newStatus, [
            Invoice::STATUS_DRAFT,
            Invoice::STATUS_PENDING,
            Invoice::STATUS_PROCESSING,
            Invoice::STATUS_PAID,
            Invoice::STATUS_FAILED
        ])) {
            throw new \Exception('无效的状态值');
        }

        // 更新状态
        $this->invoiceRepository->updateStatus($id, $newStatus);
        $invoice = $this->invoiceRepository->find($id);

        return [
            'id' => $invoice->id,
            'status' => $invoice->status,
            'status_name' => $invoice->status_name
        ];
    }



    /**
     * 格式化账单数据
     *
     * @param Invoice $invoice
     * @return array
     */
    private function formatInvoiceData(Invoice $invoice): array
    {
        $invoiceArray = $invoice->toArray();

        // 格式化时间字段
        $invoiceArray['created_at'] = $invoice->created_at ? $invoice->created_at->format('Y-m-d H:i:s') : null;
        $invoiceArray['updated_at'] = $invoice->updated_at ? $invoice->updated_at->format('Y-m-d H:i:s') : null;
        $invoiceArray['paid_at'] = $invoice->paid_at ? $invoice->paid_at->format('Y-m-d H:i:s') : null;

        // 格式化关联数据时间字段
        if (isset($invoiceArray['student']) && $invoice->student) {
            $invoiceArray['student']['created_at'] = $invoice->student->formatted_created_at;
            $invoiceArray['student']['updated_at'] = $invoice->student->formatted_updated_at;
        }

        if (isset($invoiceArray['course']) && $invoice->course) {
            $invoiceArray['course']['created_at'] = $invoice->course->formatted_created_at;
            $invoiceArray['course']['updated_at'] = $invoice->course->formatted_updated_at;
        }

        if (isset($invoiceArray['teacher']) && $invoice->teacher) {
            $invoiceArray['teacher']['created_at'] = $invoice->teacher->formatted_created_at;
            $invoiceArray['teacher']['updated_at'] = $invoice->teacher->formatted_updated_at;
        }

        // 添加额外字段
        $invoiceArray['course_name'] = $invoice->course->name ?? '未知课程';
        $invoiceArray['student_name'] = $invoice->student->name ?? '未知学生';
        $invoiceArray['student_email'] = $invoice->student->email ?? '';
        $invoiceArray['teacher_name'] = $invoice->teacher->name ?? '未知教师';
        $invoiceArray['status_name'] = $invoice->status_name;

        return $invoiceArray;
    }

    /**
     * 记录查询日志
     *
     * @param string $message
     * @param $query
     * @param object $user
     * @param $result
     */
    private function logQuery(string $message, $query, object $user, $result): void
    {
        Log::channel('sql')->info($message, [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
            'user_id' => $user->user_id ?? null,
            'user_role' => $user->role ?? null,
            'total_results' => $result->total(),
            'current_page' => $result->currentPage(),
            'per_page' => $result->perPage(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'timestamp' => now()->toDateTimeString()
        ]);
    }
}
