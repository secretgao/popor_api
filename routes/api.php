<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// 公开路由（不需要认证）
Route::prefix('auth')->group(function () {
    // 用户登录
    Route::post('/login', [AuthController::class, 'login']);
});

// 支付相关路由（无需认证）
Route::prefix('payment')->group(function () {
    // 获取支付配置
    Route::get('/config', [App\Http\Controllers\PaymentController::class, 'getConfig']);
    
    // Webhook 处理
    Route::post('/webhook', [App\Http\Controllers\PaymentController::class, 'webhook']);
});

// 需要认证的路由
Route::middleware('api.token')->group(function () {
    Route::prefix('auth')->group(function () {
        // 用户登出
        Route::post('/logout', [AuthController::class, 'logout']);
        
        // 获取当前用户信息
        Route::get('/me', [AuthController::class, 'me']);
        
        // 刷新令牌
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });
    
    // 仪表盘统计路由
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [DashboardController::class, 'getStats']);
    });
    
    // 支付相关路由（需要认证）
    Route::prefix('payment')->group(function () {
        // 处理支付
        Route::post('/process', [App\Http\Controllers\PaymentController::class, 'processPayment']);
        
        // 获取支付详情
        Route::get('/charge/{chargeId}', [App\Http\Controllers\PaymentController::class, 'getCharge']);
        
        // 退款
        Route::post('/refund', [App\Http\Controllers\PaymentController::class, 'refund']);
    });
    
    // 课程管理路由
    Route::prefix('courses')->group(function () {
        Route::get('/', [App\Http\Controllers\CourseController::class, 'index']);
        Route::post('/', [App\Http\Controllers\CourseController::class, 'store']);
        Route::put('/{id}', [App\Http\Controllers\CourseController::class, 'update']);
        Route::put('/{id}/status', [App\Http\Controllers\CourseController::class, 'updateStatus']);
    });
    
    // 学生管理路由
    Route::prefix('students')->group(function () {
        Route::get('/', [App\Http\Controllers\StudentController::class, 'index']);
        Route::post('/', [App\Http\Controllers\StudentController::class, 'store']);
        Route::get('/my-courses', [App\Http\Controllers\StudentController::class, 'myCourses']);
        Route::get('/{id}', [App\Http\Controllers\StudentController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\StudentController::class, 'update']);
        Route::get('/{id}/courses', [App\Http\Controllers\StudentController::class, 'courses']);
        Route::get('/{id}/invoices', [App\Http\Controllers\StudentController::class, 'invoices']);
        Route::put('/{id}/status', [App\Http\Controllers\StudentController::class, 'updateStatus']);
    });
    
    // 账单管理路由
    Route::prefix('invoices')->group(function () {
        Route::get('/', [App\Http\Controllers\InvoiceController::class, 'index']);
        Route::post('/', [App\Http\Controllers\InvoiceController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\InvoiceController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\InvoiceController::class, 'update']);
        Route::put('/{id}/status', [App\Http\Controllers\InvoiceController::class, 'updateStatus']);
        Route::delete('/{id}', [App\Http\Controllers\InvoiceController::class, 'destroy']);
    });
});

// 测试路由
Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'API系统运行正常',
        'timestamp' => now()->toDateTimeString()
    ]);
});

// Swagger 文档路由
Route::get('/swagger.json', function () {
    $swaggerJsonFile = storage_path('api-docs/swagger.json');
    if (file_exists($swaggerJsonFile)) {
        return response()->file($swaggerJsonFile, [
            'Content-Type' => 'application/json'
        ]);
    }
    return response()->json(['error' => 'Swagger JSON not found'], 404);
});
