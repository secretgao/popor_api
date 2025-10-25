<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_user_can_login_with_valid_credentials()
    {
        // 创建测试用户
        $user = $this->createStudent([
            'username' => 'testuser',
            'password' => bcrypt('password123')
        ]);

        // 发送登录请求
        $response = $this->postJson('/api/auth/login', [
            'username' => 'testuser',
            'password' => 'password123',
            'role' => 'student'
        ]);

        // 断言响应
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user' => [
                    'id',
                    'username',
                    'name',
                    'email',
                    'role'
                ],
                'token'
            ]
        ]);

        $response->assertJson(['success' => true]);
    }

    /** @test */
    public function test_user_cannot_login_with_invalid_credentials()
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => 'nonexistent',
            'password' => 'wrongpassword',
            'role' => 'student'
        ]);

        $response->assertStatus(401);
        $response->assertJson(['success' => false]);
    }

    /** @test */
    public function test_login_requires_all_fields()
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['username', 'password', 'role']);
    }

    /** @test */
    public function test_teacher_can_login()
    {
        $teacher = $this->createTeacher([
            'username' => 'teacher1',
            'password' => bcrypt('password123')
        ]);

        $response = $this->postJson('/api/auth/login', [
            'username' => 'teacher1',
            'password' => 'password123',
            'role' => 'teacher'
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /** @test */
    public function test_user_can_get_own_info()
    {
        $user = $this->createStudent();

        $response = $this->actingAsUser($user)
            ->getJson('/api/auth/me');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'username',
                'name',
                'email',
                'role'
            ]
        ]);
    }

    /** @test */
    public function test_unauthenticated_user_cannot_get_info()
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
        $response->assertJson(['success' => false]);
    }

    /** @test */
    public function test_user_can_logout()
    {
        $user = $this->createStudent();

        $response = $this->actingAsUser($user)
            ->postJson('/api/auth/logout');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /** @test */
    public function test_user_can_refresh_token()
    {
        $user = $this->createStudent();

        $response = $this->actingAsUser($user)
            ->postJson('/api/auth/refresh');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'token'
            ]
        ]);
    }

    /** @test */
    public function test_login_with_invalid_role()
    {
        $user = $this->createStudent([
            'username' => 'testuser',
            'password' => bcrypt('password123')
        ]);

        $response = $this->postJson('/api/auth/login', [
            'username' => 'testuser',
            'password' => 'password123',
            'role' => 'invalid_role'
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['role']);
    }

    /** @test */
    public function test_login_with_wrong_role()
    {
        $teacher = $this->createTeacher([
            'username' => 'teacher1',
            'password' => bcrypt('password123')
        ]);

        // 尝试以学生角色登录教师账户
        $response = $this->postJson('/api/auth/login', [
            'username' => 'teacher1',
            'password' => 'password123',
            'role' => 'student'
        ]);

        $response->assertStatus(401);
        $response->assertJson(['success' => false]);
    }
}
