<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Passport\Passport;
use OpenApi\Annotations as OA;
use App\Models\AdminUser;

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
class AuthController extends Controller
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
    public function login(Request $request)
    {
        // 验证请求参数
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:50',
            'password' => 'required|string',
            'role' => 'required|string|in:teacher,student'
        ], [
            'username.required' => '用户名不能为空',
            'username.string' => '用户名必须是字符串',
            'username.max' => '用户名不能超过50个字符',
            'password.required' => '密码不能为空',
            'password.string' => '密码必须是字符串',
            'role.required' => '角色不能为空',
            'role.string' => '角色必须是字符串',
            'role.in' => '角色只能是teacher或student'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '参数验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $username = $request->input('username');
        $password = $request->input('password');
        $role = $request->input('role');

        try {
            // 根据角色选择不同的用户表进行验证
            if ($role === 'teacher') {
                return $this->authenticateTeacher($username, $password);
            } else {
                return $this->authenticateStudent($username, $password);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '登录失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 教师登录验证
     *
     * @param string $username
     * @param string $password
     * @return \Illuminate\Http\JsonResponse
     */
    private function authenticateTeacher($username, $password)
    {
        // 从admin_users表查询教师用户
        $teacher = AdminUser::where('name', $username)
            ->where('is_del',false)
            ->first();

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => '教师用户不存在或者禁用'
            ], 401);
        }

        // 验证密码
        if (!Hash::check($password, $teacher->password)) {
            return response()->json([
                'success' => false,
                'message' => '密码错误'
            ], 401);
        }

        // 生成访问令牌
        $token = $this->generateToken($teacher, 'teacher');

        return response()->json([
            'success' => true,
            'message' => '教师登录成功',
            'data' => [
                'user' => [
                    'id' => $teacher->id,
                    'username' => $teacher->username,
                    'name' => $teacher->name,
                    'email' => $teacher->email,
                    'role' => 'teacher',
                    'avatar' => $teacher->avatar ?? null
                ],
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => 3600 // 1小时
            ]
        ], 200);
    }

    /**
     * 学生登录验证
     *
     * @param string $username
     * @param string $password
     * @return \Illuminate\Http\JsonResponse
     */
    private function authenticateStudent($username, $password)
    {
        // 从users表查询学生用户
        $student = User::where('username', $username)
            ->where('is_active',true)
            ->first();

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => '学生用户不存在或被禁用'
            ], 401);
        }

        // 验证密码
        if (!Hash::check($password, $student->password)) {
            return response()->json([
                'success' => false,
                'message' => '密码错误'
            ], 401);
        }

        // 检查用户是否激活
        if (!$student->is_active) {
            return response()->json([
                'success' => false,
                'message' => '账户已被禁用'
            ], 401);
        }

        // 生成访问令牌
        $token = $this->generateToken($student, 'student');

        return response()->json([
            'success' => true,
            'message' => '学生登录成功',
            'data' => [
                'user' => [
                    'id' => $student->id,
                    'username' => $student->username,
                    'name' => $student->name,
                    'email' => $student->email,
                    'role' => 'student',
                    'avatar' => $student->avatar ?? null
                ],
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => 3600 // 1小时
            ]
        ], 200);
    }

    /**
     * 生成访问令牌
     *
     * @param object $user
     * @param string $role
     * @return string
     */
    private function generateToken($user, $role)
    {
        // 使用简单的JWT令牌（base64编码）
        $payload = [
            'user_id' => $user->id,
            'username' => $user->username,
            'name' => $user->name ?? $user->username,
            'email' => $user->email ?? null,
            'role' => $role,
            'exp' => time() + 3600, // 1小时后过期
            'iat' => time() // 签发时间
        ];

        // 使用base64编码生成简单令牌
        $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload_encoded = base64_encode(json_encode($payload));
        $signature = base64_encode(hash_hmac('sha256', $header . '.' . $payload_encoded, config('app.key'), true));

        return $header . '.' . $payload_encoded . '.' . $signature;
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
        try {
            // 对于简单的JWT令牌，我们不需要撤销操作
            // 客户端只需要删除本地存储的令牌即可

            return response()->json([
                'success' => true,
                'message' => '登出成功'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '登出失败：' . $e->getMessage()
            ], 500);
        }
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
    public function me(Request $request)
    {
        try {
            $user = $request->attributes->get('auth_user');

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => '未登录'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->user_id,
                        'username' => $user->username,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'avatar' => null
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取用户信息失败：' . $e->getMessage()
            ], 500);
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
    public function refresh(Request $request)
    {
        try {
            $user = $request->attributes->get('auth_user');

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => '未登录'
                ], 401);
            }

            // 生成新令牌
            $token = $this->generateToken($user, $user->role);

            return response()->json([
                'success' => true,
                'message' => '令牌刷新成功',
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => 3600
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '令牌刷新失败：' . $e->getMessage()
            ], 500);
        }
    }
}
