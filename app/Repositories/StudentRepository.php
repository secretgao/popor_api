<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class StudentRepository extends BaseRepository
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * 获取学生列表（带分页和搜索）
     *
     * @param int $perPage
     * @param string|null $search
     * @return LengthAwarePaginator
     */
    public function getStudentsWithPagination(int $perPage = 10, ?string $search = null): LengthAwarePaginator
    {
        $query = $this->model->query()
            ->withCount(['courses', 'invoices'])
            ->select([
                'id',
                'username',
                'name',
                'email',
                'created_at',
                'is_active'
            ]);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('id', 'desc')->paginate($perPage);
    }

    /**
     * 根据ID获取学生详情
     *
     * @param int $id
     * @return User|null
     */
    public function getStudentById(int $id): ?User
    {
        return $this->model->where('id', $id)
            ->select([
                'id',
                'username',
                'name',
                'email',
                'created_at',
                'is_active'
            ])
            ->first();
    }

    /**
     * 创建学生
     *
     * @param array $data
     * @return User
     */
    public function createStudent(array $data): User
    {
        $data['role'] = 0; // 学生角色
        $data['is_active'] = true;
        
        return $this->create($data);
    }

    /**
     * 检查用户名是否已存在
     *
     * @param string $username
     * @param int|null $excludeId
     * @return bool
     */
    public function usernameExists(string $username, ?int $excludeId = null): bool
    {
        $query = $this->model->where('username', $username);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return $query->exists();
    }

    /**
     * 检查邮箱是否已存在
     *
     * @param string $email
     * @param int|null $excludeId
     * @return bool
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $query = $this->model->where('email', $email);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return $query->exists();
    }

    /**
     * 更新学生状态
     *
     * @param int $id
     * @param bool $isActive
     * @return bool
     */
    public function updateStatus(int $id, bool $isActive): bool
    {
        return $this->model->where('id', $id)->update(['is_active' => $isActive]);
    }

    /**
     * 获取学生的课程列表
     *
     * @param int $studentId
     * @return Collection
     */
    public function getStudentCourses(int $studentId): Collection
    {
        $student = $this->find($studentId);
        
        if (!$student) {
            return collect();
        }
        
        return $student->courses()->with(['teacher'])->get();
    }

    /**
     * 获取学生的账单列表
     *
     * @param int $studentId
     * @return Collection
     */
    public function getStudentInvoices(int $studentId): Collection
    {
        $student = $this->find($studentId);
        
        if (!$student) {
            return collect();
        }
        
        return $student->invoices()->with(['course.teacher'])->get();
    }

    /**
     * 获取学生的选课记录（分页）
     *
     * @param int $studentId
     * @param int $perPage
     * @param int $page
     * @return LengthAwarePaginator
     */
    public function getStudentCourseStudents(int $studentId, int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        $student = $this->find($studentId);
        
        if (!$student) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }
        
        return $student->courseStudents()
            ->with(['course.teacher'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * 获取学生统计信息
     *
     * @param int $studentId
     * @return array
     */
    public function getStudentStats(int $studentId): array
    {
        $student = $this->model->withCount([
            'courses',
            'invoices',
            'invoices as pending_invoices_count' => function ($query) {
                $query->where('status', 1);
            },
            'invoices as paid_invoices_count' => function ($query) {
                $query->where('status', 3);
            }
        ])->find($studentId);
        
        if (!$student) {
            return [
                'courses_count' => 0,
                'invoices_count' => 0,
                'pending_invoices' => 0,
                'paid_invoices' => 0
            ];
        }
        
        return [
            'courses_count' => $student->courses_count,
            'invoices_count' => $student->invoices_count,
            'pending_invoices' => $student->pending_invoices_count,
            'paid_invoices' => $student->paid_invoices_count
        ];
    }
}
