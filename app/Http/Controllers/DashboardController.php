<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AdminUser;
use App\Models\User;
use App\Models\Course;
use App\Models\Invoice;

/**
 * @OA\Tag(
 *     name="Dashboard",
 *     description="仪表盘统计接口"
 * )
 */
class DashboardController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/dashboard/stats",
     *     summary="获取仪表盘统计数据",
     *     tags={"Dashboard"},
     *     security={{"bearer_token":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="teachers_count", type="integer", description="教师数量"),
     *                 @OA\Property(property="students_count", type="integer", description="学生数量"),
     *                 @OA\Property(property="courses_count", type="integer", description="课程数量"),
     *                 @OA\Property(property="invoices_count", type="integer", description="账单数量"),
     *                 @OA\Property(property="pending_invoices", type="integer", description="待支付账单数量"),
     *                 @OA\Property(property="paid_invoices", type="integer", description="已支付账单数量")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="未授权"
     *     )
     * )
     */
    public function getStats(Request $request)
    {
        try {
            $user = $request->attributes->get('auth_user');

            // 获取教师数量（从 admin_users 表）
            $teachersCount = AdminUser::count();

            // 获取学生数量（从 users 表，role = 0）
            $studentsCount = User::count();

            // 获取课程数量
            $coursesCount = Course::count();

            // 获取账单总数
            $invoicesCount = Invoice::count();

            // 获取待支付账单数量（status = 0）
            $pendingInvoices = Invoice::where('status', 0)->count();

            // 获取已支付账单数量（status = 2）
            $paidInvoices = Invoice::where('status', 2)->count();

            // 如果是教师，只显示自己相关的数据
            if ($user->role === 'teacher') {
                // 只统计自己的课程
                $coursesCount = Course::where('teacher_id', $user->user_id)->count();

                // 只统计自己课程的账单
                $invoicesCount = Invoice::whereHas('course', function($query) use ($user) {
                    $query->where('teacher_id', $user->user_id);
                })->count();

                $pendingInvoices = Invoice::whereHas('course', function($query) use ($user) {
                    $query->where('teacher_id', $user->user_id);
                })->where('status', 0)->count();

                $paidInvoices = Invoice::whereHas('course', function($query) use ($user) {
                    $query->where('teacher_id', $user->user_id);
                })->where('status', 2)->count();
            }

            // 如果是学生，只显示自己的账单数据
            if ($user->role === 'student') {
                $invoicesCount = Invoice::where('student_id', $user->user_id)->count();

                $pendingInvoices = Invoice::where('student_id', $user->user_id)
                    ->where('status', 0)
                    ->count();

                $paidInvoices = Invoice::where('student_id', $user->user_id)
                    ->where('status', 2)
                    ->count();

                // 学生不显示教师数量，但可以显示所有课程数量
                $teachersCount = 0;
                // 学生可以看到所有课程数量
                $coursesCount = Course::count();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'teachers_count' => $teachersCount,
                    'students_count' => $studentsCount,
                    'courses_count' => $coursesCount,
                    'invoices_count' => $invoicesCount,
                    'pending_invoices' => $pendingInvoices,
                    'paid_invoices' => $paidInvoices
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取统计数据失败: ' . $e->getMessage()
            ], 500);
        }
    }
}
