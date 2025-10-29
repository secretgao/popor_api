<?php

namespace App\Repositories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CourseRepository extends BaseRepository
{
    public function __construct(Course $model)
    {
        parent::__construct($model);
    }

    /**
     * 获取课程列表（带分页和过滤）
     *
     * @param int $perPage
     * @param int|null $teacherId
     * @return LengthAwarePaginator
     */
    public function getCoursesWithPagination(int $perPage = 10, ?int $teacherId = null): LengthAwarePaginator
    {
        $query = $this->model->with(['teacher', 'students'])
            ->withCount('students')
            ->select([
                'id',
                'name',
                'year_month',
                'fee',
                'teacher_id',
                'created_at',
                'updated_at',
                'is_del',
            ]);

        if ($teacherId) {
            $query->where('teacher_id', $teacherId);
        }

        return $query->orderBy('id', 'desc')->paginate($perPage);
    }

    /**
     * 根据教师ID获取课程
     *
     * @param int $teacherId
     * @param array $columns
     * @return Collection
     */
    public function getCoursesByTeacher(int $teacherId, array $columns = ['*']): Collection
    {
        return $this->model->where('teacher_id', $teacherId)->get($columns);
    }

    /**
     * 检查课程是否存在且属于指定教师
     *
     * @param int $courseId
     * @param int $teacherId
     * @return bool
     */
    public function existsByTeacher(int $courseId, int $teacherId): bool
    {
        return $this->model->where('id', $courseId)
            ->where('teacher_id', $teacherId)
            ->exists();
    }

    /**
     * 更新课程状态
     *
     * @param int $courseId
     * @param bool $isDel
     * @return bool
     */
    public function updateStatus(int $courseId, bool $isDel): bool
    {
        return $this->model->where('id', $courseId)->update(['is_del' => $isDel]);
    }
}
