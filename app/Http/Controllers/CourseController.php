<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Course;
use App\Models\AdminUser;
use App\Models\User;

/**
 * @OA\Tag(
 *     name="Courses",
 *     description="课程管理接口"
 * )
 */
class CourseController extends Controller
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
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10);
            $teacherId = $request->get('teacher_id');

            $query = Course::with(['teacher'])
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

            $courses = $query->orderby('id','desc')->paginate($perPage);

            // 添加学生数量和教师名称
            $courses->getCollection()->transform(function($course) {
                $course->price = $course->fee;
                $course->teacher_name = $course->teacher ? $course->teacher->name : '未知教师';
                $course->students_count = $course->students()->count();

                // 使用模型访问器获取格式化时间
                $course->created_at = $course->formatted_created_at;
                $course->updated_at = $course->formatted_updated_at;

                return $course;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'courses' => $courses->items(),
                    'pagination' => [
                        'current_page' => $courses->currentPage(),
                        'per_page' => $courses->perPage(),
                        'total' => $courses->total(),
                        'last_page' => $courses->lastPage()
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取课程列表失败: ' . $e->getMessage()
            ], 500);
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
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:200',
                'year_month' => 'required|string|size:6',
                'fee' => 'required|numeric|min:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $this->requireAuthUser($request);
            if ($user instanceof \Illuminate\Http\JsonResponse) {
                return $user;
            }

            // 检查教师是否在 admin_users 表中存在
            $teacher = AdminUser::find($user->user_id);

            if (!$teacher) {
                return response()->json([
                    'success' => false,
                    'message' => '教师不存在或权限不足'
                ], 403);
            }

            // 创建课程
            $course = Course::create([
                'name' => $request->name,
                'year_month' => $request->year_month,
                'fee' => $request->fee,
                'teacher_id' => $user->user_id
            ]);

            // 加载关联数据
            $course->load(['teacher']);

            // 添加关联数据
            $course->price = $course->fee;
            $course->teacher_name = $course->teacher ? $course->teacher->name : '未知教师';
            $course->students_count = 0;

            // 使用模型访问器获取格式化时间
            $course->created_at = $course->formatted_created_at;
            $course->updated_at = $course->formatted_updated_at;

            return response()->json([
                'success' => true,
                'data' => ['course' => $course]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '创建课程失败: ' . $e->getMessage()
            ], 500);
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
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:200',
                'year_month' => 'required|string|size:6',
                'fee' => 'required|numeric|min:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $this->requireAuthUser($request);
            if ($user instanceof \Illuminate\Http\JsonResponse) {
                return $user;
            }

            $course = Course::where('id', $id)
                ->where('teacher_id', $user->user_id)
                ->first();

            if (!$course) {
                return response()->json([
                    'success' => false,
                    'message' => '课程不存在或您没有权限修改'.$user->user_id
                ], 404);
            }

            // 更新课程
            $course->update([
                'name' => $request->name,
                'year_month' => $request->year_month,
                'fee' => $request->fee
            ]);

            // 加载关联数据
            $course->load(['teacher']);

            // 添加关联数据
            $course->price = $course->fee;
            $course->teacher_name = $course->teacher ? $course->teacher->name : '未知教师';
            $course->students_count = $course->students()->count();

            // 使用模型访问器获取格式化时间
            $course->created_at = $course->formatted_created_at;
            $course->updated_at = $course->formatted_updated_at;

            return response()->json([
                'success' => true,
                'message' => '课程更新成功',
                'data' => ['course' => $course]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '更新课程失败: ' . $e->getMessage()
            ], 500);
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
    public function updateStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'is_del' => 'required|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $course = Course::find($id);

            if (!$course) {
                return response()->json([
                    'success' => false,
                    'message' => '课程不存在'
                ], 404);
            }

            Course::query()->where('id',$id)->update([
                'is_del' => $request->is_del
            ]);

            return response()->json([
                'success' => true,
                'message' => '课程状态更新成功',
                'data' => [
                    'id' => $course->id,
                    'name' => $course->name,
                    'is_del' => $course->is_del
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('更新课程状态失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '更新课程状态失败'
            ], 500);
        }
    }

}
