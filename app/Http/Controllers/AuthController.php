<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use App\Services\ApiResponseService;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="Education API System",
 *     version="1.0.0",
 *     description="教育管理系统API文档"
 * )
 * @OA\Server(
 *     url="http://api.localhost",
 *     description="开发环境"
 * )
 * @OA\SecurityScheme(
 *     securityScheme="bearer_token",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
class AuthController extends BaseController
{
    /**
     * 用户登录接口
     *
     * @OA\Post(
     *     path="/api/auth/login",
     *     summary="用户登录",
     *     description="用户登录接口，支持教师和学生两种角色",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username","password","role"},
     *             @OA\Property(property="username", type="string", description="用户名", example="admin"),
     *             @OA\Property(property="password", type="string", description="密码", example="password123"),
     *             @OA\Property(property="role", type="string", description="角色", enum={"teacher","student"}, example="teacher")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="登录成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="登录成功"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="username", type="string", example="admin"),
     *                     @OA\Property(property="name", type="string", example="管理员"),
     *                     @OA\Property(property="email", type="string", example="admin@example.com"),
     *                     @OA\Property(property="role", type="string", example="teacher"),
     *                     @OA\Property(property="avatar", type="string", nullable=true, example=null)
     *                 ),
     *                 @OA\Property(property="token", type="string", example="base64_encoded_token"),
     *                 @OA\Property(property="token_type", type="string", example="Bearer"),
     *                 @OA\Property(property="expires_in", type="integer", example=3600)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="参数验证失败",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="参数验证失败"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="username", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="password", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="role", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="认证失败",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="用户名或密码错误")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="服务器错误",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="登录失败：服务器内部错误")
     *         )
     *     )
     * )
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request, AuthService $authService)
    {
        try {
            $result = $authService->login($request->all());

            return $this->success($result, $result['user']['role'] === 'teacher' ? '教师登录成功' : '学生登录成功');
        } catch (\Exception $e) {
            return $this->handleServiceException($e);
        }
    }


    /**
     * 用户登出接口
     *
     * @OA\Post(
     *     path="/api/auth/logout",
     *     summary="用户登出",
     *     description="用户登出接口，清除当前访问令牌",
     *     tags={"Authentication"},
     *     security={{"bearer_token":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="登出成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="登出成功")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="未认证",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="未认证")
     *         )
     *     )
     * )
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // 对于简单的JWT令牌，我们不需要撤销操作
        // 客户端只需要删除本地存储的令牌即可
        return $this->success(null, '登出成功');
    }

    /**
     * 获取当前用户信息
     *
     * @OA\Get(
     *     path="/api/auth/me",
     *     summary="获取当前用户信息",
     *     description="获取当前登录用户的详细信息",
     *     tags={"Authentication"},
     *     security={{"bearer_token":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="获取成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="管理员"),
     *                     @OA\Property(property="email", type="string", example="admin@example.com"),
     *                     @OA\Property(property="role", type="string", example="teacher"),
     *                     @OA\Property(property="avatar", type="string", nullable=true, example=null)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="未认证",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="未认证")
     *         )
     *     )
     * )
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request, AuthService $authService)
    {
        try {
            $user = $this->requireAuthUser($request);
            if ($user instanceof \Illuminate\Http\JsonResponse) {
                return $user;
            }

            $userInfo = $authService->getUserInfo($user);

            return $this->success(['user' => $userInfo], '获取用户信息成功');
        } catch (\Exception $e) {
            return $this->handleServiceException($e);
        }
    }

    /**
     * 刷新令牌
     *
     * @OA\Post(
     *     path="/api/auth/refresh",
     *     summary="刷新访问令牌",
     *     description="刷新当前用户的访问令牌",
     *     tags={"Authentication"},
     *     security={{"bearer_token":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="刷新成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="令牌刷新成功"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="token", type="string", example="new_base64_encoded_token"),
     *                 @OA\Property(property="token_type", type="string", example="Bearer"),
     *                 @OA\Property(property="expires_in", type="integer", example=3600)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="未认证",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="未认证")
     *         )
     *     )
     * )
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh(Request $request, AuthService $authService)
    {
        try {
            $user = $this->requireAuthUser($request);
            if ($user instanceof \Illuminate\Http\JsonResponse) {
                return $user;
            }

            $tokenData = $authService->refreshToken($user);

            return $this->success($tokenData, '令牌刷新成功');
        } catch (\Exception $e) {
            return $this->handleServiceException($e);
        }
    }
}
