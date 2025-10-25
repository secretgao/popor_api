<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Course;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_teacher_can_get_dashboard_stats()
    {
        $teacher = $this->createTeacher();
        
        // 创建一些学生
        $students = collect([
            $this->createStudent(),
            $this->createStudent(),
            $this->createStudent()
        ]);

        // 创建一些课程
        $courses = collect([
            $this->createCourse(['teacher_id' => $teacher->id]),
            $this->createCourse(['teacher_id' => $teacher->id]),
            $this->createCourse(['teacher_id' => $teacher->id])
        ]);

        // 创建一些账单
        $invoices = collect([
            $this->createInvoice([
                'student_id' => $students[0]->id,
                'course_id' => $courses[0]->id,
                'teacher_id' => $teacher->id,
                'amount' => 500.00
            ]),
            $this->createInvoice([
                'student_id' => $students[1]->id,
                'course_id' => $courses[1]->id,
                'teacher_id' => $teacher->id,
                'amount' => 600.00
            ]),
            $this->createInvoice([
                'student_id' => $students[2]->id,
                'course_id' => $courses[2]->id,
                'teacher_id' => $teacher->id,
                'amount' => 700.00
            ])
        ]);

        $response = $this->actingAsUser($teacher)
            ->getJson('/api/dashboard/stats');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'teacher_count',
                'student_count',
                'course_count',
                'invoice_count',
                'total_revenue'
            ]
        ]);

        $response->assertJson(['success' => true]);
        
        $data = $response->json('data');
        $this->assertEquals(1, $data['teacher_count']); // 当前教师
        $this->assertEquals(3, $data['student_count']);
        $this->assertEquals(3, $data['course_count']);
        $this->assertEquals(3, $data['invoice_count']);
        $this->assertEquals(1800.00, $data['total_revenue']); // 500 + 600 + 700
    }

    /** @test */
    public function test_student_can_get_dashboard_stats()
    {
        $student = $this->createStudent();
        $teacher = $this->createTeacher();
        
        // 创建一些课程
        $courses = collect([
            $this->createCourse(['teacher_id' => $teacher->id]),
            $this->createCourse(['teacher_id' => $teacher->id])
        ]);

        // 创建一些账单
        $invoices = collect([
            $this->createInvoice([
                'student_id' => $student->id,
                'course_id' => $courses[0]->id,
                'teacher_id' => $teacher->id,
                'amount' => 500.00
            ]),
            $this->createInvoice([
                'student_id' => $student->id,
                'course_id' => $courses[1]->id,
                'teacher_id' => $teacher->id,
                'amount' => 600.00
            ])
        ]);

        $response = $this->actingAsUser($student)
            ->getJson('/api/dashboard/stats');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'course_count',
                'invoice_count'
            ]
        ]);

        $response->assertJson(['success' => true]);
        
        $data = $response->json('data');
        $this->assertEquals(2, $data['course_count']);
        $this->assertEquals(2, $data['invoice_count']);
        
        // 学生不应该看到教师和学生数量
        $this->assertArrayNotHasKey('teacher_count', $data);
        $this->assertArrayNotHasKey('student_count', $data);
        $this->assertArrayNotHasKey('total_revenue', $data);
    }

    /** @test */
    public function test_dashboard_stats_exclude_deleted_courses()
    {
        $teacher = $this->createTeacher();
        
        // 创建正常课程
        $this->createCourse(['teacher_id' => $teacher->id]);
        $this->createCourse(['teacher_id' => $teacher->id]);
        
        // 创建已删除的课程
        $this->createCourse([
            'teacher_id' => $teacher->id,
            'is_del' => true
        ]);

        $response = $this->actingAsUser($teacher)
            ->getJson('/api/dashboard/stats');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertEquals(2, $data['course_count']); // 只计算未删除的课程
    }

    /** @test */
    public function test_dashboard_stats_exclude_inactive_students()
    {
        $teacher = $this->createTeacher();
        
        // 创建活跃学生
        $this->createStudent(['is_active' => true]);
        $this->createStudent(['is_active' => true]);
        
        // 创建非活跃学生
        $this->createStudent(['is_active' => false]);

        $response = $this->actingAsUser($teacher)
            ->getJson('/api/dashboard/stats');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertEquals(2, $data['student_count']); // 只计算活跃学生
    }

    /** @test */
    public function test_dashboard_stats_calculate_revenue_correctly()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);

        // 创建不同状态的账单
        $this->createInvoice([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'amount' => 500.00,
            'status' => 'paid'
        ]);

        $this->createInvoice([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'amount' => 600.00,
            'status' => 'pending'
        ]);

        $this->createInvoice([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'amount' => 700.00,
            'status' => 'paid'
        ]);

        $response = $this->actingAsUser($teacher)
            ->getJson('/api/dashboard/stats');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertEquals(1200.00, $data['total_revenue']); // 只计算已支付的账单
    }

    /** @test */
    public function test_dashboard_stats_handle_empty_data()
    {
        $teacher = $this->createTeacher();

        $response = $this->actingAsUser($teacher)
            ->getJson('/api/dashboard/stats');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertEquals(1, $data['teacher_count']); // 当前教师
        $this->assertEquals(0, $data['student_count']);
        $this->assertEquals(0, $data['course_count']);
        $this->assertEquals(0, $data['invoice_count']);
        $this->assertEquals(0.00, $data['total_revenue']);
    }

    /** @test */
    public function test_dashboard_stats_require_authentication()
    {
        $response = $this->getJson('/api/dashboard/stats');

        $response->assertStatus(401);
        $response->assertJson(['success' => false]);
    }

    /** @test */
    public function test_dashboard_stats_teacher_role_sees_all_stats()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);
        $invoice = $this->createInvoice([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'amount' => 500.00
        ]);

        $response = $this->actingAsUser($teacher)
            ->getJson('/api/dashboard/stats');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // 教师应该看到所有统计数据
        $this->assertArrayHasKey('teacher_count', $data);
        $this->assertArrayHasKey('student_count', $data);
        $this->assertArrayHasKey('course_count', $data);
        $this->assertArrayHasKey('invoice_count', $data);
        $this->assertArrayHasKey('total_revenue', $data);
    }

    /** @test */
    public function test_dashboard_stats_student_role_limited_stats()
    {
        $student = $this->createStudent();
        $teacher = $this->createTeacher();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);
        $invoice = $this->createInvoice([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'amount' => 500.00
        ]);

        $response = $this->actingAsUser($student)
            ->getJson('/api/dashboard/stats');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // 学生只能看到课程和账单数量
        $this->assertArrayHasKey('course_count', $data);
        $this->assertArrayHasKey('invoice_count', $data);
        
        // 学生不应该看到教师和学生数量
        $this->assertArrayNotHasKey('teacher_count', $data);
        $this->assertArrayNotHasKey('student_count', $data);
        $this->assertArrayNotHasKey('total_revenue', $data);
    }

    /** @test */
    public function test_dashboard_stats_include_all_courses_for_student()
    {
        $student = $this->createStudent();
        $teacher1 = $this->createTeacher();
        $teacher2 = $this->createTeacher();
        
        // 创建不同教师的课程
        $this->createCourse(['teacher_id' => $teacher1->id]);
        $this->createCourse(['teacher_id' => $teacher2->id]);
        $this->createCourse(['teacher_id' => $teacher1->id]);

        $response = $this->actingAsUser($student)
            ->getJson('/api/dashboard/stats');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertEquals(3, $data['course_count']); // 学生应该看到所有课程
    }

    /** @test */
    public function test_dashboard_stats_handle_large_datasets()
    {
        $teacher = $this->createTeacher();
        
        // 创建大量数据
        for ($i = 1; $i <= 100; $i++) {
            $this->createStudent();
            $this->createCourse(['teacher_id' => $teacher->id]);
        }

        $response = $this->actingAsUser($teacher)
            ->getJson('/api/dashboard/stats');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertEquals(1, $data['teacher_count']);
        $this->assertEquals(100, $data['student_count']);
        $this->assertEquals(100, $data['course_count']);
    }
}
