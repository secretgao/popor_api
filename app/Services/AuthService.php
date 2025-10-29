<?php

namespace App\Services;

use App\Models\User;
use App\Models\AdminUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class AuthService
{
    /**
     * 用户登录
     *
     * @param array $credentials
     * @return array
     * @throws \Exception
     */
    public function login(array $credentials): array
    {
        $username = $credentials['username'];
        $password = $credentials['password'];
        $role = $credentials['role'];

        if ($role === 'teacher') {
            return $this->authenticateTeacher($username, $password);
        } else {
            return $this->authenticateStudent($username, $password);
        }
    }


    /**
     * 教师登录验证
     *
     * @param string $username
     * @param string $password
     * @return array
     * @throws \Exception
     */
    private function authenticateTeacher(string $username, string $password): array
    {
        $teacher = AdminUser::where('name', $username)
            ->where('is_del', false)
            ->first();

        if (!$teacher) {
            throw new \Exception('教师用户不存在或者禁用');
        }

        if (!Hash::check($password, $teacher->password)) {
            throw new \Exception('密码错误');
        }

        $token = $this->generateToken($teacher, 'teacher');

        return [
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
            'expires_in' => 3600
        ];
    }

    /**
     * 学生登录验证
     *
     * @param string $username
     * @param string $password
     * @return array
     * @throws \Exception
     */
    private function authenticateStudent(string $username, string $password): array
    {
        $student = User::where('username', $username)
            ->where('is_active', true)
            ->first();

        if (!$student) {
            throw new \Exception('学生用户不存在或被禁用');
        }

        if (!Hash::check($password, $student->password)) {
            throw new \Exception('密码错误');
        }

        if (!$student->is_active) {
            throw new \Exception('账户已被禁用');
        }

        $token = $this->generateToken($student, 'student');

        return [
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
            'expires_in' => 3600
        ];
    }

    /**
     * 生成访问令牌
     *
     * @param object $user
     * @param string $role
     * @return string
     */
    private function generateToken($user, string $role): string
    {
        $payload = [
            'user_id' => $user->id,
            'username' => $user->username,
            'name' => $user->name ?? $user->username,
            'email' => $user->email ?? null,
            'role' => $role,
            'exp' => time() + 3600,
            'iat' => time()
        ];

        $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload_encoded = base64_encode(json_encode($payload));
        $signature = base64_encode(hash_hmac('sha256', $header . '.' . $payload_encoded, config('app.key'), true));

        return $header . '.' . $payload_encoded . '.' . $signature;
    }

    /**
     * 获取用户信息
     *
     * @param object $user
     * @return array
     */
    public function getUserInfo(object $user): array
    {
        return [
            'id' => $user->user_id,
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'avatar' => null
        ];
    }

    /**
     * 刷新令牌
     *
     * @param object $user
     * @return array
     */
    public function refreshToken(object $user): array
    {
        $token = $this->generateToken($user, $user->role);

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 3600
        ];
    }
}
