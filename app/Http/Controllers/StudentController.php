<?php

namespace App\Http\Controllers;

use App\Services\StudentService;
use App\Http\Requests\Student\CreateStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Requests\Student\UpdateStudentStatusRequest;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Students",
 *     description="学生管理接口"
 * )
 */
class StudentController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/api/students",
     *     summary="获取学生列表",
     *     tags={"Students"},
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="页码",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="每页数量",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="搜索关键词",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="students", type="array", @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="username", type="string"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="email", type="string"),
     *                     @OA\Property(property="courses_count", type="integer"),
     *                     @OA\Property(property="invoices_count", type="integer"),
     *                     @OA\Property(property="created_at", type="string")
     *                 )),
     *                 @OA\Property(property="pagination", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request, StudentService $studentService)
    {
        try {
            $result = $studentService->getStudents($request);

            return $this->success($result, '获取学生列表成功');
        } catch (\Exception $e) {
            return $this->handleServiceException($e);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/students",
     *     summary="创建学生",
     *     tags={"Students"},
     *     security={{"bearer_token":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="username", type="string", description="用户名"),
     *             @OA\Property(property="name", type="string", description="姓名"),
     *             @OA\Property(property="email", type="string", description="邮箱"),
     *             @OA\Property(property="password", type="string", description="密码")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功"
     *     )
     * )
     */
    public function store(CreateStudentRequest $request, StudentService $studentService)
    {
        try {
            $result = $studentService->createStudent($request);

            return $this->success($result, '学生创建成功');
        } catch (\Exception $e) {
            return $this->handleServiceException($e);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/students/{id}",
     *     summary="获取学生详情",
     *     tags={"Students"},
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="学生ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功"
     *     )
     * )
     */
    public function show($id, StudentService $studentService)
    {
        try {
            $result = $studentService->getStudent((int)$id);

            return $this->success($result, '获取学生详情成功');
        } catch (\Exception $e) {
            return $this->handleServiceException($e);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/students/{id}/courses",
     *     summary="获取学生的课程列表",
     *     tags={"Students"},
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="学生ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功"
     *     )
     * )
     */
    public function courses($id, StudentService $studentService)
    {
        try {
            $result = $studentService->getStudentCourses((int)$id);

            return $this->success($result, '获取学生课程成功');
        } catch (\Exception $e) {
            return $this->handleServiceException($e);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/students/{id}/invoices",
     *     summary="获取学生的账单列表",
     *     tags={"Students"},
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="学生ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功"
     *     )
     * )
     */
    public function invoices($id, StudentService $studentService)
    {
        try {
            $result = $studentService->getStudentInvoices((int)$id);

            return $this->success($result, '获取学生账单成功');
        } catch (\Exception $e) {
            return $this->handleServiceException($e);
        }
    }

    /**
     * 获取当前学生的课程列表
     */
    public function myCourses(Request $request, StudentService $studentService)
    {
        try {
            $user = $this->requireAuthUser($request);
            if ($user instanceof \Illuminate\Http\JsonResponse) {
                return $user;
            }

            $result = $studentService->getMyCourses($request, $user);

            return $this->success($result, '获取我的课程成功');
        } catch (\Exception $e) {
            return $this->handleServiceException($e);
        }
    }


    /**
     * @OA\Put(
     *     path="/api/students/{id}/status",
     *     summary="更新学生状态",
     *     tags={"Students"},
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="学生ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"is_active"},
     *             @OA\Property(property="is_active", type="boolean", description="是否启用")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="状态更新成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="学生状态更新成功"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="学生不存在",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="学生不存在")
     *         )
     *     )
     * )
     */
    public function updateStatus(UpdateStudentStatusRequest $request, $id, StudentService $studentService)
    {
        try {
            $result = $studentService->updateStudentStatus($request, (int)$id);

            return $this->success($result, '学生状态更新成功');
        } catch (\Exception $e) {
            return $this->handleServiceException($e);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/students/{id}",
     *     summary="更新学生信息",
     *     tags={"Students"},
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="学生ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username", "name", "email"},
     *             @OA\Property(property="username", type="string", description="用户名"),
     *             @OA\Property(property="name", type="string", description="姓名"),
     *             @OA\Property(property="email", type="string", description="邮箱"),
     *             @OA\Property(property="password", type="string", description="密码（可选）")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="学生信息更新成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="学生信息更新成功"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="学生不存在",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="学生不存在")
     *         )
     *     )
     * )
     */
    public function update(UpdateStudentRequest $request, $id, StudentService $studentService)
    {
        try {
            $result = $studentService->updateStudent($request, (int)$id);

            return $this->success($result, '学生信息更新成功');
        } catch (\Exception $e) {
            return $this->handleServiceException($e);
        }
    }
}
