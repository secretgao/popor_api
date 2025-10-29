<?php

namespace App\Repositories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class InvoiceRepository extends BaseRepository
{
    public function __construct(Invoice $model)
    {
        parent::__construct($model);
    }

    /**
     * 获取账单列表（带分页和权限过滤）
     *
     * @param int $perPage
     * @param string $userRole
     * @param int $userId
     * @param int|null $status
     * @param int|null $studentId
     * @return LengthAwarePaginator
     */
    public function getInvoicesWithPagination(
        int $perPage = 10,
        string $userRole,
        int $userId,
        ?int $status = null,
        ?int $studentId = null
    ): LengthAwarePaginator {
        $query = $this->model->with(['student', 'course', 'teacher'])
            ->select([
                'id',
                'student_id',
                'course_id',
                'teacher_id',
                'amount',
                'status',
                'year_month',
                'paid_at',
                'currency',
                'created_at'
            ]);

        // 权限过滤
        if ($userRole === 'student') {
            $query->where('student_id', $userId)
                ->whereIn('status', [
                    Invoice::STATUS_PENDING,
                    Invoice::STATUS_PROCESSING,
                    Invoice::STATUS_PAID,
                    Invoice::STATUS_FAILED
                ]);
        } elseif ($userRole === 'teacher') {
            $query->where('teacher_id', $userId);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($studentId) {
            $query->where('student_id', $studentId);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * 检查账单是否存在且属于指定用户
     *
     * @param int $invoiceId
     * @param string $userRole
     * @param int $userId
     * @return bool
     */
    public function existsByUser(int $invoiceId, string $userRole, int $userId): bool
    {
        $query = $this->model->where('id', $invoiceId);

        if ($userRole === 'student') {
            $query->where('student_id', $userId);
        } elseif ($userRole === 'teacher') {
            $query->where('teacher_id', $userId);
        }

        return $query->exists();
    }

    /**
     * 检查学生和课程的账单是否已存在
     *
     * @param int $studentId
     * @param int $courseId
     * @return bool
     */
    public function existsByStudentAndCourse(int $studentId, int $courseId): bool
    {
        return $this->model->where('student_id', $studentId)
            ->where('course_id', $courseId)
            ->exists();
    }

    /**
     * 更新账单状态
     *
     * @param int $invoiceId
     * @param int $status
     * @return bool
     */
    public function updateStatus(int $invoiceId, int $status): bool
    {
        return $this->model->where('id', $invoiceId)->update(['status' => $status]);
    }

    /**
     * 根据教师ID获取账单统计
     *
     * @param int $teacherId
     * @return array
     */
    public function getStatsByTeacher(int $teacherId): array
    {
        $total = $this->model->where('teacher_id', $teacherId)->count();
        $pending = $this->model->where('teacher_id', $teacherId)
            ->where('status', Invoice::STATUS_PENDING)->count();
        $paid = $this->model->where('teacher_id', $teacherId)
            ->where('status', Invoice::STATUS_PAID)->count();
        $failed = $this->model->where('teacher_id', $teacherId)
            ->where('status', Invoice::STATUS_FAILED)->count();

        return [
            'total' => $total,
            'pending' => $pending,
            'paid' => $paid,
            'failed' => $failed
        ];
    }

    /**
     * 根据学生ID获取账单统计
     *
     * @param int $studentId
     * @return array
     */
    public function getStatsByStudent(int $studentId): array
    {
        $total = $this->model->where('student_id', $studentId)->count();
        $pending = $this->model->where('student_id', $studentId)
            ->where('status', Invoice::STATUS_PENDING)->count();
        $paid = $this->model->where('student_id', $studentId)
            ->where('status', Invoice::STATUS_PAID)->count();
        $failed = $this->model->where('student_id', $studentId)
            ->where('status', Invoice::STATUS_FAILED)->count();

        return [
            'total' => $total,
            'pending' => $pending,
            'paid' => $paid,
            'failed' => $failed
        ];
    }
}
