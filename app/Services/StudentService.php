<?php

namespace App\Services;

use App\Repositories\StudentRepository;
use App\Http\Requests\Student\CreateStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Requests\Student\UpdateStudentStatusRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StudentService
{
    protected $studentRepository;

    public function __construct(StudentRepository $studentRepository)
    {
        $this->studentRepository = $studentRepository;
    }

    /**
     * 获取学生列表
     *
     * @param Request $request
     * @return array
     */
    public function getStudents(Request $request): array
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search');

        $students = $this->studentRepository->getStudentsWithPagination($perPage, $search);

        // 添加关联数据并格式化时间
        $students->getCollection()->transform(function($student) {
            return $this->formatStudentData($student);
        });

        return [
            'students' => $students->items(),
            'pagination' => [
                'current_page' => $students->currentPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
                'last_page' => $students->lastPage()
            ]
        ];
    }

    /**
     * 创建学生
     *
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function createStudent(CreateStudentRequest $request): array
    {
        // 检查用户名是否已存在
        if ($this->studentRepository->usernameExists($request->username)) {
            throw new \Exception('用户名已存在');
        }

        // 检查邮箱是否已存在
        if ($this->studentRepository->emailExists($request->email)) {
            throw new \Exception('邮箱已存在');
        }

        $student = $this->studentRepository->createStudent([
            'username' => $request->username,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);

        return ['student' => $this->formatStudentData($student)];
    }

    /**
     * 获取学生详情
     *
     * @param int $id
     * @return array
     * @throws \Exception
     */
    public function getStudent(int $id): array
    {
        $student = $this->studentRepository->getStudentById($id);

        if (!$student) {
            throw new \Exception('学生不存在');
        }

        return ['student' => $this->formatStudentData($student)];
    }

    /**
     * 更新学生信息
     *
     * @param Request $request
     * @param int $id
     * @return array
     * @throws \Exception
     */
    public function updateStudent(UpdateStudentRequest $request, int $id): array
    {
        $student = $this->studentRepository->find($id);
        if (!$student) {
            throw new \Exception('学生不存在');
        }

        // 检查用户名是否被其他学生使用
        if ($this->studentRepository->usernameExists($request->username, $id)) {
            throw new \Exception('用户名已被其他学生使用');
        }

        // 检查邮箱是否被其他学生使用
        if ($this->studentRepository->emailExists($request->email, $id)) {
            throw new \Exception('邮箱已被其他学生使用');
        }

        // 准备更新数据
        $updateData = [
            'username' => $request->username,
            'name' => $request->name,
            'email' => $request->email
        ];

        // 如果提供了密码，则更新密码
        if ($request->password) {
            $updateData['password'] = Hash::make($request->password);
        }

        $this->studentRepository->update($id, $updateData);
        $student = $this->studentRepository->find($id);

        return [
            'id' => $student->id,
            'username' => $student->username,
            'name' => $student->name,
            'email' => $student->email
        ];
    }

    /**
     * 更新学生状态
     *
     * @param Request $request
     * @param int $id
     * @return array
     * @throws \Exception
     */
    public function updateStudentStatus(UpdateStudentStatusRequest $request, int $id): array
    {
        $student = $this->studentRepository->find($id);
        if (!$student) {
            throw new \Exception('学生不存在');
        }

        $this->studentRepository->updateStatus($id, $request->is_active);

        return [
            'id' => $student->id,
            'name' => $student->name,
            'is_active' => $request->is_active
        ];
    }

    /**
     * 获取学生的课程列表
     *
     * @param int $studentId
     * @return array
     * @throws \Exception
     */
    public function getStudentCourses(int $studentId): array
    {
        $student = $this->studentRepository->find($studentId);
        if (!$student) {
            throw new \Exception('学生不存在');
        }

        $courses = $this->studentRepository->getStudentCourses($studentId);

        // 格式化时间字段
        $courses->transform(function($course) {
            $course->price = $course->fee;
            $course->teacher_name = $course->teacher ? $course->teacher->name : '未知教师';
            $course->enrolled_at = $course->pivot->created_at ? $course->pivot->created_at->format('Y-m-d H:i:s') : null;

            // 使用模型访问器获取格式化时间
            $course->created_at = $course->formatted_created_at;
            $course->updated_at = $course->formatted_updated_at;

            return $course;
        });

        return ['courses' => $courses];
    }

    /**
     * 获取学生的账单列表
     *
     * @param int $studentId
     * @return array
     * @throws \Exception
     */
    public function getStudentInvoices(int $studentId): array
    {
        $student = $this->studentRepository->find($studentId);
        if (!$student) {
            throw new \Exception('学生不存在');
        }

        $invoices = $this->studentRepository->getStudentInvoices($studentId);

        // 格式化时间字段并添加关联数据
        $invoices->transform(function($invoice) {
            $invoice->course_name = $invoice->course ? $invoice->course->name : '未知课程';
            $invoice->teacher_name = $invoice->course && $invoice->course->teacher ? $invoice->course->teacher->name : '未知教师';
            $invoice->status_name = $this->getStatusName($invoice->status);

            // 使用模型访问器获取格式化时间
            $invoice->created_at = $invoice->formatted_created_at;
            $invoice->updated_at = $invoice->formatted_updated_at;
            $invoice->paid_at = $invoice->formatted_paid_at;

            return $invoice;
        });

        return ['invoices' => $invoices];
    }

    /**
     * 获取当前学生的课程列表
     *
     * @param Request $request
     * @param object $user
     * @return array
     * @throws \Exception
     */
    public function getMyCourses(Request $request, object $user): array
    {
        $userId = $user->user_id ?? null;
        
        if (!$userId) {
            throw new \Exception('无法获取用户ID');
        }

        $student = $this->studentRepository->find($userId);
        if (!$student) {
            throw new \Exception('用户不存在');
        }

        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);

        $courseStudents = $this->studentRepository->getStudentCourseStudents($userId, $perPage, $page);

        // 格式化数据
        $courses = $courseStudents->getCollection()->map(function ($courseStudent) {
            $course = $courseStudent->course;
            return [
                'id' => $course->id,
                'name' => $course->name,
                'year_month' => $course->year_month,
                'fee' => $course->fee,
                'teacher_name' => $course->teacher ? $course->teacher->name : '未知教师',
                'created_at' => $courseStudent->formatted_created_at,
            ];
        });

        return [
            'courses' => $courses,
            'pagination' => [
                'current_page' => $courseStudents->currentPage(),
                'per_page' => $courseStudents->perPage(),
                'total' => $courseStudents->total(),
                'last_page' => $courseStudents->lastPage()
            ]
        ];
    }


    /**
     * 格式化学生数据
     *
     * @param $student
     * @return mixed
     */
    private function formatStudentData($student)
    {
        $student->courses_count = $student->courses_count; // 使用withCount的结果
        $student->invoices_count = $student->invoices_count; // 使用withCount的结果
        $student->created_at = $student->formatted_created_at;

        return $student;
    }

    /**
     * 获取状态名称
     *
     * @param int $status
     * @return string
     */
    private function getStatusName(int $status): string
    {
        switch ($status) {
            case 0: return '待支付';
            case 1: return '支付中'; 
            case 2: return '支付成功';
            case 3: return '支付失败';
            default: return '待支付';
        }
    }
}
