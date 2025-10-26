<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\Invoice;
use App\Models\Course;
use App\Models\User;

/**
 * @OA\Tag(
 *     name="Invoices",
 *     description="账单管理接口"
 * )
 */
class InvoiceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/invoices",
     *     summary="获取账单列表",
     *     tags={"Invoices"},
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
     *         name="status",
     *         in="query",
     *         description="账单状态",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="student_id",
     *         in="query",
     *         description="学生ID",
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
            $status = $request->get('status');
            $studentId = $request->get('student_id');
            
            $user = $this->requireAuthUser($request);
            if ($user instanceof \Illuminate\Http\JsonResponse) {
                return $user;
            }

            $query = Invoice::with(['student', 'course', 'teacher'])
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

            // 如果是学生，只能看到自己的账单，且状态不等于0（已发送的账单）
            if ($user->role === 'student') {
                $query->where('student_id', $user->user_id);
                $query->whereIn('status', [
                    Invoice::STATUS_PENDING,
                    Invoice::STATUS_PROCESSING,
                    Invoice::STATUS_PAID,
                    Invoice::STATUS_FAILED
                ]);
            }

            // 如果是教师，只能看到自己的账单
            if ($user->role === 'teacher') {
                $query->where('teacher_id', $user->user_id);
            }

            if ($status !== null) {
                $query->where('status', $status);
            }

            if ($studentId) {
                $query->where('student_id', $studentId);
            }

            $invoices = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // 记录SQL查询日志
            Log::channel('sql')->info('账单列表查询', [
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
                'user_id' => $user->user_id ?? null,
                'user_role' => $user->role ?? null,
                'total_results' => $invoices->total(),
                'current_page' => $invoices->currentPage(),
                'per_page' => $invoices->perPage(),
                'url' => request()->fullUrl(),
                'method' => request()->method(),
                'timestamp' => now()->toDateTimeString()
            ]);

            // 添加关联数据和状态名称
            $invoices->getCollection()->transform(function($invoice) {
                // 调试信息
                Log::channel('sql')->info('账单数据处理调试', [
                    'invoice_id' => $invoice->id,
                    'teacher_id' => $invoice->teacher_id,
                    'teacher_loaded' => $invoice->teacher ? 'yes' : 'no',
                    'teacher_name' => $invoice->teacher ? $invoice->teacher->name : 'null'
                ]);

                // 添加关联数据
                $invoice->course_name = $invoice->course->name ?? '未知课程';
                $invoice->student_name = $invoice->student->name ?? '未知学生';
                $invoice->student_email = $invoice->student->email ?? '';
                $invoice->teacher_name = $invoice->teacher->name ?? '未知教师';

                // 添加状态名称
                $invoice->status_name = $invoice->status_name;

                return $invoice;
            });

            // 简化时间格式化，先测试基本功能
            $formattedInvoices = $invoices->getCollection()->map(function($invoice) {
                $invoiceArray = $invoice->toArray();

                // 直接格式化时间字段，避免访问器问题
                $invoiceArray['created_at'] = $invoice->created_at ? $invoice->created_at->format('Y-m-d H:i:s') : null;
                $invoiceArray['updated_at'] = $invoice->updated_at ? $invoice->updated_at->format('Y-m-d H:i:s') : null;
                $invoiceArray['paid_at'] = $invoice->paid_at ? $invoice->paid_at->format('Y-m-d H:i:s') : null;

                return $invoiceArray;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'invoices' => $formattedInvoices->toArray(),
                    'pagination' => [
                        'current_page' => $invoices->currentPage(),
                        'per_page' => $invoices->perPage(),
                        'total' => $invoices->total(),
                        'last_page' => $invoices->lastPage()
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            // 记录详细错误信息
            Log::error('InvoiceController index error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取账单列表失败: ' . $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/invoices",
     *     summary="创建账单",
     *     tags={"Invoices"},
     *     security={{"bearer_token":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="student_id", type="integer", description="学生ID"),
     *             @OA\Property(property="course_id", type="integer", description="课程ID"),
     *             @OA\Property(property="amount", type="number", description="账单金额"),
     *             @OA\Property(property="description", type="string", description="账单描述"),
     *             @OA\Property(property="year_month", type="string", description="年月(YYYYMM)")
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
                'student_id' => 'required|integer|exists:users,id',
                'course_id' => 'required|integer|exists:courses,id',
                'amount' => 'required|numeric|min:0',
                'year_month' => 'required|string|size:6',
                'description' => 'nullable|string|max:500'
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

            $course = Course::where('id', $request->course_id)
                ->first();

            if (!$course) {
                return response()->json([
                    'success' => false,
                    'message' => '课程不存在'
                ], 403);
            }

            // 检查是否已存在相同的账单（基于数据库唯一约束：student_id + course_id）
            $existingInvoice = Invoice::where('student_id', $request->student_id)
                ->where('course_id', $request->course_id)
                ->first();

            if ($existingInvoice) {
                return response()->json([
                    'success' => false,
                    'message' => '该学生在此课程的账单已存在，每个学生每个课程只能有一个账单'
                ], 403);
            }

            // 创建账单
            $invoice = Invoice::create([
                'student_id' => $request->student_id,
                'course_id' => $request->course_id,
                'teacher_id' => $user->user_id, // 记录教师ID
                'amount' => $request->amount,
                'year_month' => $request->year_month,
                'status' => Invoice::STATUS_DRAFT, // 待发送
                'description' => $request->description,
                'currency' => 'JPY'
            ]);

            // 加载关联数据
            $invoice->load(['student', 'course', 'teacher']);

            // 手动格式化所有时间字段
            $invoiceArray = $invoice->toArray();

            // 格式化主数据时间字段
            $invoiceArray['created_at'] = $invoice->formatted_created_at;
            $invoiceArray['updated_at'] = $invoice->formatted_updated_at;
            $invoiceArray['sent_at'] = $invoice->formatted_sent_at;
            $invoiceArray['paid_at'] = $invoice->formatted_paid_at;

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

            return response()->json([
                'success' => true,
                'data' => ['invoice' => $invoiceArray],
                'message' => '账单创建成功'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '创建账单失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/invoices/{id}",
     *     summary="获取账单详情",
     *     tags={"Invoices"},
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="账单ID",
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
            $user = $this->requireAuthUser($request);
            if ($user instanceof \Illuminate\Http\JsonResponse) {
                return $user;
            }

            $query = Invoice::with(['student', 'course', 'teacher'])
                ->where('id', $id);

            // 权限检查
            if ($user->role === 'student') {
                $query->where('student_id', $user->user_id);
            } elseif ($user->role === 'teacher') {
                $query->where('teacher_id', $user->user_id);
            }

            $invoice = $query->first();

            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => '账单不存在或您没有权限查看'
                ], 404);
            }

            // 手动格式化所有时间字段
            $invoiceArray = $invoice->toArray();

            // 格式化主数据时间字段
            $invoiceArray['created_at'] = $invoice->formatted_created_at;
            $invoiceArray['updated_at'] = $invoice->formatted_updated_at;
            $invoiceArray['sent_at'] = $invoice->formatted_sent_at;
            $invoiceArray['paid_at'] = $invoice->formatted_paid_at;

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

            return response()->json([
                'success' => true,
                'data' => ['invoice' => $invoiceArray]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取账单详情失败: ' . $e->getMessage()
            ], 500);
        }
    }    /**
     * @OA\Put(
     *     path="/api/invoices/{id}",
     *     summary="更新账单",
     *     tags={"Invoices"},
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="账单ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="amount", type="number", description="账单金额"),
     *             @OA\Property(property="description", type="string", description="账单描述"),
     *             @OA\Property(property="year_month", type="string", description="年月(YYYYMM)")
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
                'amount' => 'required|numeric|min:0',
                'year_month' => 'required|string|size:6'
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

            // 验证账单权限
            $invoice = Invoice::where('id', $id)
                ->where('teacher_id', $user->user_id)
                ->first();

            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => '账单不存在或您没有权限修改'
                ], 404);
            }

            if ($invoice->status === Invoice::STATUS_PROCESSING) {
                return response()->json([
                    'success' => false,
                    'message' => '支付中的账单无法修改'
                ], 400);
            }

            // 更新账单
            $invoice->update([
                'amount' => $request->amount,
                'year_month' => $request->year_month
            ]);

            return response()->json([
                'success' => true,
                'message' => '账单更新成功'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '更新账单失败: ' . $e->getMessage()
            ], 500);
        }
    }



    /**
     * 获取状态名称
     */
    private function getStatusName($status)
    {
        switch ($status) {
            case Invoice::STATUS_DRAFT: return Invoice::STATUS_DRAFT;      // 待发送
            case Invoice::STATUS_PENDING: return Invoice::STATUS_PENDING;  // 待支付
            case Invoice::STATUS_PROCESSING: return Invoice::STATUS_PROCESSING; // 支付中
            case Invoice::STATUS_PAID: return Invoice::STATUS_PAID;        // 支付成功
            case Invoice::STATUS_FAILED: return Invoice::STATUS_FAILED;    // 支付失败
            default: return Invoice::STATUS_DRAFT;
        }
    }

    /**
     * @OA\Put(
     *     path="/api/invoices/{id}/status",
     *     summary="更新账单状态",
     *     tags={"Invoices"},
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="账单ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="integer", description="新状态值")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="状态更新成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="状态更新成功"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="status", type="integer"),
     *                 @OA\Property(property="status_name", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="账单不存在"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="权限不足"
     *     )
     * )
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $user = $this->requireAuthUser($request);
            if ($user instanceof \Illuminate\Http\JsonResponse) {
                return $user;
            }

            $invoice = Invoice::findOrFail($id);

            // 权限检查：只有教师可以更新自己创建的账单状态
            if ($user->role === 'teacher' && $invoice->teacher_id !== $user->user_id) {
                return response()->json([
                    'success' => false,
                    'message' => '权限不足，只能更新自己创建的账单'
                ], 403);
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
                return response()->json([
                    'success' => false,
                    'message' => '无效的状态值'
                ], 400);
            }

            // 更新状态
            $invoice->status = $newStatus;
            $invoice->save();

            return response()->json([
                'success' => true,
                'message' => '状态更新成功',
                'data' => [
                    'id' => $invoice->id,
                    'status' => $invoice->status,
                    'status_name' => $invoice->status_name
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => '账单不存在'
            ], 404);
        } catch (\Exception $e) {
            Log::error('更新账单状态失败', [
                'invoice_id' => $id,
                'new_status' => $request->input('status'),
                'user_id' => $user->user_id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '更新状态失败'
            ], 500);
        }
    }
}


