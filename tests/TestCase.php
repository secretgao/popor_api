<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }

    /**
     * 创建认证用户
     */
    protected function createAuthUser($role = 'student', $attributes = [])
    {
        $defaultAttributes = [
            'username' => $this->faker->userName,
            'name' => $this->faker->name,
            'email' => $this->faker->email,
            'password' => bcrypt('password123'),
            'role' => $role === 'teacher' ? 1 : 0,
            'is_active' => true,
        ];

        $attributes = array_merge($defaultAttributes, $attributes);

        return \App\Models\User::create($attributes);
    }

    /**
     * 创建认证的教师用户
     */
    protected function createTeacher($attributes = [])
    {
        return $this->createAuthUser('teacher', $attributes);
    }

    /**
     * 创建认证的学生用户
     */
    protected function createStudent($attributes = [])
    {
        return $this->createAuthUser('student', $attributes);
    }

    /**
     * 创建课程
     */
    protected function createCourse($attributes = [])
    {
        $defaultAttributes = [
            'name' => $this->faker->sentence(3),
            'year_month' => $this->faker->date('Y-m'),
            'fee' => $this->faker->randomFloat(2, 100, 1000),
            'teacher_id' => $this->createTeacher()->id,
            'is_del' => false,
        ];

        $attributes = array_merge($defaultAttributes, $attributes);

        return \App\Models\Course::create($attributes);
    }

    /**
     * 创建账单
     */
    protected function createInvoice($attributes = [])
    {
        $student = $this->createStudent();
        $course = $this->createCourse();

        $defaultAttributes = [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'teacher_id' => $course->teacher_id,
            'amount' => $course->fee,
            'year_month' => $this->faker->date('Y-m'),
            'status' => 'pending',
            'sent_at' => null,
            'paid_at' => null,
        ];

        $attributes = array_merge($defaultAttributes, $attributes);

        return \App\Models\Invoice::create($attributes);
    }

    /**
     * 生成JWT令牌
     */
    protected function generateJwtToken($user)
    {
        $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = base64_encode(json_encode([
            'user_id' => $user->id,
            'username' => $user->username,
            'role' => $user->role,
            'iat' => time(),
            'exp' => time() + 3600
        ]));
        
        $signature = base64_encode(hash_hmac('sha256', $header . '.' . $payload, config('app.key'), true));
        
        return $header . '.' . $payload . '.' . $signature;
    }

    /**
     * 使用认证用户发送请求
     */
    protected function actingAsUser($user, $driver = null)
    {
        $token = $this->generateJwtToken($user);
        
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);
    }

    /**
     * 断言JSON响应结构
     */
    protected function assertJsonResponse($response, $expectedStatus = 200)
    {
        $response->assertStatus($expectedStatus);
        $response->assertJsonStructure([
            'success',
            'message'
        ]);
    }

    /**
     * 断言成功响应
     */
    protected function assertSuccessResponse($response, $expectedStatus = 200)
    {
        $this->assertJsonResponse($response, $expectedStatus);
        $response->assertJson(['success' => true]);
    }

    /**
     * 断言错误响应
     */
    protected function assertErrorResponse($response, $expectedStatus = 400)
    {
        $this->assertJsonResponse($response, $expectedStatus);
        $response->assertJson(['success' => false]);
    }
}