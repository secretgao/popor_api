<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Course;
use App\Models\Invoice;
use App\Models\CourseStudent;

/**
 * @OA\Tag(
 *     name="Students",
 *     description="学生管理接口"
 * )
 */
class StudentController extends Controller
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
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10);
            $search = $request->get('search');

            $query = User::query()->select([
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

            $students = $query->orderby('id','desc')->paginate($perPage);

            // 添加关联数据并格式化时间
            $students->getCollection()->transform(function($student) {
                $student->courses_count = $student->courses()->count();
                $student->invoices_count = $student->invoices()->count();
                // 使用模型访问器获取格式化时间
                $student->created_at = $student->formatted_created_at;

                return $student;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'students' => $students->items(),
                    'pagination' => [
                        'current_page' => $students->currentPage(),
                        'per_page' => $students->perPage(),
                        'total' => $students->total(),
                        'last_page' => $students->lastPage()
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取学生列表失败: ' . $e->getMessage()
            ], 500);
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
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'username' => 'required|string|max:255|unique:users,username',
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:6'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $student = User::create([
                'username' => $request->username,
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 0, // 学生角色
                'is_active' => true
            ]);

            // 使用模型访问器获取格式化时间
            $student->created_at = $student->formatted_created_at;

            return response()->json([
                'success' => true,
                'data' => ['student' => $student],
                'message' => '学生创建成功'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '创建学生失败: ' . $e->getMessage()
            ], 500);
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
    public function show($id)
    {
        try {
            $student = User::where('id', $id)
                ->select([
                    'id',
                    'username',
                    'name',
                    'email',
                    'created_at'
                ])
                ->first();

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => '学生不存在'
                ], 404);
            }

            // 添加关联数据并格式化时间
            $student->courses_count = $student->courses()->count();
            $student->invoices_count = $student->invoices()->count();

            // 使用模型访问器获取格式化时间
            $student->created_at = $student->formatted_created_at;

            return response()->json([
                'success' => true,
                'data' => ['student' => $student]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取学生详情失败: ' . $e->getMessage()
            ], 500);
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
    public function courses($id)
    {
        try {
            $student = User::find($id);
            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => '学生不存在'
                ], 404);
            }

            $courses = $student->courses()->with(['teacher'])->get();

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

            return response()->json([
                'success' => true,
                'data' => ['courses' => $courses]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取学生课程失败: ' . $e->getMessage()
            ], 500);
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
    public function invoices($id)
    {
        try {
            $student = User::find($id);
            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => '学生不存在'
                ], 404);
            }

            $invoices = $student->invoices()->with(['course.teacher'])->get();

            // 格式化时间字段并添加关联数据
            $invoices->transform(function($invoice) {
                $invoice->course_name = $invoice->course ? $invoice->course->name : '未知课程';
                $invoice->teacher_name = $invoice->course && $invoice->course->teacher ? $invoice->course->teacher->name : '未知教师';
                $invoice->status_name = $this->getStatusName($invoice->status);

                // 使用模型访问器获取格式化时间
                $invoice->created_at = $invoice->formatted_created_at;
                $invoice->updated_at = $invoice->formatted_updated_at;
                $invoice->sent_at = $invoice->formatted_sent_at;
                $invoice->paid_at = $invoice->formatted_paid_at;

                return $invoice;
            });

            return response()->json([
                'success' => true,
                'data' => ['invoices' => $invoices]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取学生账单失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取当前学生的课程列表
     */
    public function myCourses(Request $request)
    {
        try {
            $authUser = $request->attributes->get('auth_user');

            if (!$authUser) {
                return response()->json([
                    'success' => false,
                    'message' => '用户未认证'
                ], 401);
            }

            $userId = $authUser->user_id ?? null;
          
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => '无法获取用户ID，JWT载荷结构：' . json_encode($authUser)
                ], 400);
            }

            // 通过ID获取User模型实例
            $user = User::find($userId);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => '用户不存在'
                ], 404);
            }

            $perPage = $request->get('per_page', 10);
            $page = $request->get('page', 1);

            // 获取学生选课记录
            $courseStudents = $user->courseStudents()
                ->with(['course.teacher'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

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
                    'status' => $courseStudent->status ?? 1
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'courses' => $courses,
                    'pagination' => [
                        'current_page' => $courseStudents->currentPage(),
                        'per_page' => $courseStudents->perPage(),
                        'total' => $courseStudents->total(),
                        'last_page' => $courseStudents->lastPage()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('获取学生课程失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '获取课程数据失败'
            ], 500);
        }
    }

    /**
     * 获取状态名称
     */
    private function getStatusName($status)
    {
        switch ($status) {
            case 0: return '待支付';
            case 1: return '已支付';
            case 2: return '已过期';
            default: return '未知';
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
    public function updateStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'is_active' => 'required|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $student = User::query()->where('id',$id)->first();

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => '学生不存在'
                ], 404);
            }
            User::query()->where('id',$id)->update([
                'is_active' => $request->is_active
            ]);
            return response()->json([
                'success' => true,
                'message' => '学生状态更新成功',
                'data' => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'is_active' => $student->is_active
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('更新学生状态失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '更新学生状态失败'
            ], 500);
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
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'username' => 'required|string|max:255',
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'password' => 'nullable|string|min:6'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $student = User::query()->where('id',$id)->first();

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => '学生不存在'
                ], 404);
            }

            // 检查用户名是否被其他学生使用
            $existingStudent = User::query()
                ->where('username', $request->username)
                ->where('id', '!=', $id)
                ->first();

            if ($existingStudent) {
                return response()->json([
                    'success' => false,
                    'message' => '用户名已被其他学生使用'
                ], 422);
            }

            // 检查邮箱是否被其他学生使用
            $existingEmail = User::query()
                ->where('email', $request->email)
                ->where('id', '!=', $id)
                ->first();

            if ($existingEmail) {
                return response()->json([
                    'success' => false,
                    'message' => '邮箱已被其他学生使用'
                ], 422);
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

            $student->update($updateData);

            return response()->json([
                'success' => true,
                'message' => '学生信息更新成功',
                'data' => [
                    'id' => $student->id,
                    'username' => $student->username,
                    'name' => $student->name,
                    'email' => $student->email
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('更新学生信息失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '更新学生信息失败'
            ], 500);
        }
    }
}
