<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Course;
use App\Models\Invoice;
use App\Models\CourseStudent;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_complete_education_workflow()
    {
        // 1. 创建教师
        $teacher = $this->createTeacher([
            'username' => 'teacher1',
            'name' => 'Teacher One'
        ]);

        // 2. 教师登录
        $loginResponse = $this->postJson('/api/auth/login', [
            'username' => 'teacher1',
            'password' => 'password123',
            'role' => 'teacher'
        ]);

        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('data.token');

        // 3. 教师创建课程
        $courseResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ])->postJson('/api/courses', [
            'name' => 'Mathematics 101',
            'year_month' => '2024-01',
            'fee' => 500.00
        ]);

        $courseResponse->assertStatus(201);
        $courseId = $courseResponse->json('data.id');

        // 4. 教师创建学生
        $studentResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ])->postJson('/api/students', [
            'username' => 'student1',
            'name' => 'Student One',
            'email' => 'student1@example.com',
            'password' => 'password123'
        ]);

        $studentResponse->assertStatus(201);
        $studentId = $studentResponse->json('data.id');

        // 5. 教师为学生创建账单
        $invoiceResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ])->postJson('/api/invoices', [
            'student_id' => $studentId,
            'course_id' => $courseId,
            'amount' => 500.00,
            'year_month' => '2024-01'
        ]);

        $invoiceResponse->assertStatus(201);
        $invoiceId = $invoiceResponse->json('data.id');

        // 6. 学生登录
        $studentLoginResponse = $this->postJson('/api/auth/login', [
            'username' => 'student1',
            'password' => 'password123',
            'role' => 'student'
        ]);

        $studentLoginResponse->assertStatus(200);
        $studentToken = $studentLoginResponse->json('data.token');

        // 7. 学生查看自己的账单
        $studentInvoicesResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $studentToken,
            'Accept' => 'application/json'
        ])->getJson('/api/invoices');

        $studentInvoicesResponse->assertStatus(200);
        $this->assertCount(1, $studentInvoicesResponse->json('data.data'));

        // 8. 学生查看课程
        $studentCoursesResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $studentToken,
            'Accept' => 'application/json'
        ])->getJson('/api/courses');

        $studentCoursesResponse->assertStatus(200);
        $this->assertCount(1, $studentCoursesResponse->json('data.data'));

        // 9. 教师查看仪表盘统计
        $dashboardResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ])->getJson('/api/dashboard/stats');

        $dashboardResponse->assertStatus(200);
        $stats = $dashboardResponse->json('data');
        $this->assertEquals(1, $stats['teacher_count']);
        $this->assertEquals(1, $stats['student_count']);
        $this->assertEquals(1, $stats['course_count']);
        $this->assertEquals(1, $stats['invoice_count']);
    }

    /** @test */
    public function test_payment_workflow()
    {
        // 创建基础数据
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);
        $invoice = $this->createInvoice([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'amount' => 500.00
        ]);

        // 1. 获取支付配置
        $configResponse = $this->getJson('/api/payment/config');
        $configResponse->assertStatus(200);
        $this->assertArrayHasKey('omise_public_key', $configResponse->json('data'));

        // 2. 创建支付令牌
        $tokenResponse = $this->postJson('/api/payment/create-token', [
            'card' => [
                'name' => 'Test User',
                'number' => '4242424242424242',
                'expiration_month' => 12,
                'expiration_year' => 2025,
                'security_code' => '123'
            ]
        ]);

        $tokenResponse->assertStatus(200);
        $tokenId = $tokenResponse->json('data.token_id');

        // 3. 处理支付
        $paymentResponse = $this->actingAsUser($student)
            ->postJson('/api/payment/process', [
                'invoice_id' => $invoice->id,
                'token_id' => $tokenId,
                'amount' => 500.00
            ]);

        $paymentResponse->assertStatus(200);
        $chargeId = $paymentResponse->json('data.charge_id');

        // 4. 获取支付详情
        $chargeResponse = $this->actingAsUser($student)
            ->getJson("/api/payment/charge/{$chargeId}");

        $chargeResponse->assertStatus(200);
        $this->assertEquals('successful', $chargeResponse->json('data.status'));

        // 5. 验证账单状态已更新
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'paid'
        ]);
    }

    /** @test */
    public function test_soft_delete_workflow()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);

        // 1. 软删除学生
        $deleteStudentResponse = $this->actingAsUser($teacher)
            ->putJson("/api/students/{$student->id}/status", [
                'is_active' => false
            ]);

        $deleteStudentResponse->assertStatus(200);

        // 验证学生状态已更新
        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'is_active' => false
        ]);

        // 2. 软删除课程
        $deleteCourseResponse = $this->actingAsUser($teacher)
            ->putJson("/api/courses/{$course->id}/status", [
                'is_del' => true
            ]);

        $deleteCourseResponse->assertStatus(200);

        // 验证课程状态已更新
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'is_del' => true
        ]);

        // 3. 恢复学生
        $restoreStudentResponse = $this->actingAsUser($teacher)
            ->putJson("/api/students/{$student->id}/status", [
                'is_active' => true
            ]);

        $restoreStudentResponse->assertStatus(200);

        // 4. 恢复课程
        $restoreCourseResponse = $this->actingAsUser($teacher)
            ->putJson("/api/courses/{$course->id}/status", [
                'is_del' => false
            ]);

        $restoreCourseResponse->assertStatus(200);
    }

    /** @test */
    public function test_authentication_workflow()
    {
        $user = $this->createStudent([
            'username' => 'testuser',
            'password' => bcrypt('password123')
        ]);

        // 1. 登录
        $loginResponse = $this->postJson('/api/auth/login', [
            'username' => 'testuser',
            'password' => 'password123',
            'role' => 'student'
        ]);

        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('data.token');

        // 2. 获取用户信息
        $meResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ])->getJson('/api/auth/me');

        $meResponse->assertStatus(200);
        $this->assertEquals('testuser', $meResponse->json('data.username'));

        // 3. 刷新令牌
        $refreshResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ])->postJson('/api/auth/refresh');

        $refreshResponse->assertStatus(200);
        $newToken = $refreshResponse->json('data.token');

        // 4. 使用新令牌访问受保护资源
        $coursesResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $newToken,
            'Accept' => 'application/json'
        ])->getJson('/api/courses');

        $coursesResponse->assertStatus(200);

        // 5. 登出
        $logoutResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $newToken,
            'Accept' => 'application/json'
        ])->postJson('/api/auth/logout');

        $logoutResponse->assertStatus(200);

        // 6. 尝试使用过期令牌访问资源（应该失败）
        $failedResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $newToken,
            'Accept' => 'application/json'
        ])->getJson('/api/courses');

        $failedResponse->assertStatus(401);
    }

    /** @test */
    public function test_error_handling_workflow()
    {
        $teacher = $this->createTeacher();

        // 1. 测试无效认证
        $unauthorizedResponse = $this->getJson('/api/courses');
        $unauthorizedResponse->assertStatus(401);

        // 2. 测试无效数据
        $invalidDataResponse = $this->actingAsUser($teacher)
            ->postJson('/api/courses', [
                'name' => '', // 空名称
                'fee' => 'invalid' // 无效费用
            ]);

        $invalidDataResponse->assertStatus(422);
        $invalidDataResponse->assertJsonValidationErrors(['name', 'fee']);

        // 3. 测试资源不存在
        $notFoundResponse = $this->actingAsUser($teacher)
            ->getJson('/api/courses/999999');

        $notFoundResponse->assertStatus(404);

        // 4. 测试权限不足
        $student = $this->createStudent();
        $otherTeacher = $this->createTeacher();
        $course = $this->createCourse(['teacher_id' => $otherTeacher->id]);

        $forbiddenResponse = $this->actingAsUser($student)
            ->putJson("/api/courses/{$course->id}", [
                'name' => 'Unauthorized Update'
            ]);

        $forbiddenResponse->assertStatus(403);
    }
}
