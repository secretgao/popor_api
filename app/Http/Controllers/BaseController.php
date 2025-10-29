<?php

namespace App\Http\Controllers;

use App\Services\ApiResponseService;
use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

abstract class BaseController extends Controller
{
    /**
     * 获取认证用户信息
     * 
     * @param Request $request
     * @return object|null 返回用户对象，如果未认证则返回 null
     */
    protected function getAuthUser(Request $request): ?object
    {
        $user = $request->attributes->get('auth_user');
        
        if (!$user) {
            return null;
        }
        
        return $user;
    }

    /**
     * 获取认证用户ID
     * 
     * @param Request $request
     * @return int|null 返回用户ID，如果未认证则返回 null
     */
    protected function getAuthUserId(Request $request): ?int
    {
        $user = $this->getAuthUser($request);
        
        if (!$user || !isset($user->user_id)) {
            return null;
        }
        
        return $user->user_id;
    }

    /**
     * 获取认证用户角色
     * 
     * @param Request $request
     * @return string|null 返回用户角色，如果未认证则返回 null
     */
    protected function getAuthUserRole(Request $request): ?string
    {
        $user = $this->getAuthUser($request);
        
        if (!$user || !isset($user->role)) {
            return null;
        }
        
        return $user->role;
    }

    /**
     * 获取认证用户信息（失败时自动返回错误响应）
     * 
     * @param Request $request
     * @return object|JsonResponse
     */
    protected function requireAuthUser(Request $request)
    {
        $user = $this->getAuthUser($request);
        
        if (!$user) {
            return ApiResponseService::unauthorized();
        }
        
        return $user;
    }

    /**
     * 获取认证用户ID（失败时自动返回错误响应）
     * 
     * @param Request $request
     * @return int|JsonResponse
     */
    protected function requireAuthUserId(Request $request)
    {
        $userId = $this->getAuthUserId($request);
        
        if (!$userId) {
            return ApiResponseService::unauthorized();
        }
        
        return $userId;
    }

    /**
     * 获取认证用户角色（失败时自动返回错误响应）
     * 
     * @param Request $request
     * @return string|JsonResponse
     */
    protected function requireAuthUserRole(Request $request)
    {
        $role = $this->getAuthUserRole($request);
        
        if (!$role) {
            return ApiResponseService::unauthorized();
        }
        
        return $role;
    }

    /**
     * 检查用户是否为教师
     * 
     * @param Request $request
     * @return bool
     */
    protected function isTeacher(Request $request): bool
    {
        try {
            return $this->getAuthUserRole($request) === 'teacher';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 检查用户是否为学生
     * 
     * @param Request $request
     * @return bool
     */
    protected function isStudent(Request $request): bool
    {
        try {
            return $this->getAuthUserRole($request) === 'student';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 处理服务层异常
     * 
     * @param \Exception $e
     * @return JsonResponse
     */
    protected function handleServiceException(\Exception $e): JsonResponse
    {
        if ($e instanceof ApiException) {
            return $e->render();
        }

        // 记录错误日志
        \Log::error('Service Exception', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        return ApiResponseService::serverError('操作失败: ' . $e->getMessage());
    }

    /**
     * 成功响应
     * 
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     * @return JsonResponse
     */
    protected function success($data = null, string $message = '操作成功', int $statusCode = 200): JsonResponse
    {
        return ApiResponseService::success($data, $message, $statusCode);
    }

    /**
     * 错误响应
     * 
     * @param string $message
     * @param int $statusCode
     * @param mixed $errors
     * @return JsonResponse
     */
    protected function error(string $message = '操作失败', int $statusCode = 400, $errors = null): JsonResponse
    {
        return ApiResponseService::error($message, $statusCode, $errors);
    }
}
