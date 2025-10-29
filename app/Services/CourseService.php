<?php

namespace App\Services;

use App\Repositories\CourseRepository;
use App\Models\AdminUser;
use App\Http\Requests\Course\CreateCourseRequest;
use App\Http\Requests\Course\UpdateCourseRequest;
use App\Http\Requests\Course\UpdateCourseStatusRequest;
use Illuminate\Http\Request;

class CourseService
{
    protected $courseRepository;

    public function __construct(CourseRepository $courseRepository)
    {
        $this->courseRepository = $courseRepository;
    }
    /**
     * 获取课程列表
     *
     * @param Request $request
     * @param object $user
     * @return array
     */
    public function getCourses(Request $request, object $user): array
    {
        $perPage = $request->get('per_page', 10);
        $teacherId = $request->get('teacher_id');

        $courses = $this->courseRepository->getCoursesWithPagination($perPage, $teacherId);

        // 添加学生数量和教师名称
        $courses->getCollection()->transform(function($course) {
            return $this->formatCourseData($course);
        });

        return [
            'courses' => $courses->items(),
            'pagination' => [
                'current_page' => $courses->currentPage(),
                'per_page' => $courses->perPage(),
                'total' => $courses->total(),
                'last_page' => $courses->lastPage()
            ]
        ];
    }

    /**
     * 创建课程
     *
     * @param Request $request
     * @param object $user
     * @return array
     * @throws \Exception
     */
    public function createCourse(CreateCourseRequest $request, object $user): array
    {
        // 检查教师是否存在
        $teacher = AdminUser::find($user->user_id);
        if (!$teacher) {
            throw new \Exception('教师不存在或权限不足');
        }

        // 创建课程
        $course = $this->courseRepository->create([
            'name' => $request->name,
            'year_month' => $request->year_month,
            'fee' => $request->fee,
            'teacher_id' => $user->user_id
        ]);

        // 加载关联数据
        $course->load(['teacher']);

        return ['course' => $this->formatCourseData($course)];
    }

    /**
     * 更新课程
     *
     * @param Request $request
     * @param int $id
     * @param object $user
     * @return array
     * @throws \Exception
     */
    public function updateCourse(UpdateCourseRequest $request, int $id, object $user): array
    {
        if (!$this->courseRepository->existsByTeacher($id, $user->user_id)) {
            throw new \Exception('课程不存在或您没有权限修改');
        }

        // 更新课程
        $this->courseRepository->update($id, [
            'name' => $request->name,
            'year_month' => $request->year_month,
            'fee' => $request->fee
        ]);

        $course = $this->courseRepository->find($id);

        // 加载关联数据
        $course->load(['teacher']);

        return ['course' => $this->formatCourseData($course)];
    }

    /**
     * 更新课程状态
     *
     * @param Request $request
     * @param int $id
     * @return array
     * @throws \Exception
     */
    public function updateCourseStatus(UpdateCourseStatusRequest $request, int $id): array
    {
        $course = $this->courseRepository->find($id);
        if (!$course) {
            throw new \Exception('课程不存在');
        }

        $this->courseRepository->updateStatus($id, $request->is_del);

        return [
            'id' => $course->id,
            'name' => $course->name,
            'is_del' => $request->is_del
        ];
    }


    /**
     * 格式化课程数据
     *
     * @param Course $course
     * @return Course
     */
    private function formatCourseData(Course $course): Course
    {
        $course->price = $course->fee;
        $course->teacher_name = $course->teacher ? $course->teacher->name : '未知教师';
        $course->students_count = $course->students_count; // 使用withCount的结果

        // 使用模型访问器获取格式化时间
        $course->created_at = $course->formatted_created_at;
        $course->updated_at = $course->formatted_updated_at;

        return $course;
    }
}
