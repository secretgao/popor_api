<?php

namespace App\Http\Controllers;

use App\Services\CourseService;
use App\Http\Requests\Course\CreateCourseRequest;
use App\Http\Requests\Course\UpdateCourseRequest;
use App\Http\Requests\Course\UpdateCourseStatusRequest;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Courses",
 *     description="课程管理接口"
 * )
 */
class CourseController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/api/courses",
     *     summary="获取课程列表",
     *     tags={"Courses"},
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
     *         name="teacher_id",
     *         in="query",
     *         description="教师ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功"
     *     )
     * )
     */
    public function index(Request $request, CourseService $courseService)
    {
        try {
            $user = $this->requireAuthUser($request);
            if ($user instanceof \Illuminate\Http\JsonResponse) {
                return $user;
            }

            $result = $courseService->getCourses($request, $user);

            return $this->success($result, '获取课程列表成功');
        } catch (\Exception $e) {
            return $this->handleServiceException($e);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/courses",
     *     summary="创建课程",
     *     tags={"Courses"},
     *     security={{"bearer_token":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", description="课程名称"),
     *             @OA\Property(property="year_month", type="string", description="年月(YYYYMM)"),
     *             @OA\Property(property="fee", type="number", description="课程费用")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功"
     *     )
     * )
     */
    public function store(CreateCourseRequest $request, CourseService $courseService)
    {
        try {
            $user = $this->requireAuthUser($request);
            if ($user instanceof \Illuminate\Http\JsonResponse) {
                return $user;
            }

            $result = $courseService->createCourse($request, $user);

            return $this->success($result, '创建课程成功');
        } catch (\Exception $e) {
            return $this->handleServiceException($e);
        }
    }


    /**
     * @OA\Put(
     *     path="/api/courses/{id}",
     *     summary="更新课程",
     *     tags={"Courses"},
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="课程ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", description="课程名称"),
     *             @OA\Property(property="year_month", type="string", description="年月(YYYYMM)"),
     *             @OA\Property(property="fee", type="number", description="课程费用")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功"
     *     )
     * )
     */
    public function update(UpdateCourseRequest $request, $id, CourseService $courseService)
    {
        try {
            $user = $this->requireAuthUser($request);
            if ($user instanceof \Illuminate\Http\JsonResponse) {
                return $user;
            }

            $result = $courseService->updateCourse($request, (int)$id, $user);

            return $this->success($result, '课程更新成功');
        } catch (\Exception $e) {
            return $this->handleServiceException($e);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/courses/{id}/status",
     *     summary="更新课程删除状态",
     *     tags={"Courses"},
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="课程ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"is_del"},
     *             @OA\Property(property="is_del", type="boolean", description="是否删除")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="状态更新成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="课程状态更新成功"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="课程不存在",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="课程不存在")
     *         )
     *     )
     * )
     */
    public function updateStatus(UpdateCourseStatusRequest $request, $id, CourseService $courseService)
    {
        try {
            $result = $courseService->updateCourseStatus($request, (int)$id);

            return $this->success($result, '课程状态更新成功');
        } catch (\Exception $e) {
            return $this->handleServiceException($e);
        }
    }

}
