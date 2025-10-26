<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

abstract class Controller
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
            return response()->json([
                'success' => false,
                'message' => '用户未认证'
            ], 401);
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
            return response()->json([
                'success' => false,
                'message' => '用户未认证'
            ], 401);
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
            return response()->json([
                'success' => false,
                'message' => '用户未认证'
            ], 401);
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
     * 返回认证错误响应
     * 
     * @param string $message
     * @return JsonResponse
     */
    protected function authErrorResponse(string $message = '用户未认证'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], 401);
    }

    /**
     * 返回权限错误响应
     * 
     * @param string $message
     * @return JsonResponse
     */
    protected function permissionErrorResponse(string $message = '权限不足'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], 403);
    }

}
