<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Course;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class CourseStudentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_student_is_enrolled_to_course_after_successful_payment()
    {
        // 创建测试数据
        $student = $this->createStudent();
        $teacher = $this->createTeacher();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);
        $invoice = $this->createInvoice([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'amount' => 500.00,
            'status' => 0 // 待支付
        ]);

        // 模拟支付成功的 webhook
        $webhookData = [
            'object' => 'event',
            'id' => 'evnt_test_123456797',
            'type' => 'charge.complete',
            'data' => [
                'object' => 'charge',
                'id' => 'chrg_test_123456797',
                'status' => 'successful',
                'amount' => 50000,
                'currency' => 'jpy',
                'metadata' => [
                    'invoice_id' => (string)$invoice->id,
                    'user_id' => (string)$student->id
                ]
            ]
        ];

        $response = $this->postJson('/api/payment/webhook', $webhookData);

        $response->assertStatus(200);
        
        // 验证学生已注册到课程
        $this->assertDatabaseHas('course_student', [
            'student_id' => $student->id,
            'course_id' => $course->id
        ]);
    }

    /** @test */
    public function test_student_is_not_enrolled_on_failed_payment()
    {
        // 创建测试数据
        $student = $this->createStudent();
        $teacher = $this->createTeacher();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);
        $invoice = $this->createInvoice([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'amount' => 500.00,
            'status' => 1 // 支付中
        ]);

        // 模拟支付失败的 webhook
        $webhookData = [
            'object' => 'event',
            'id' => 'evnt_test_123456798',
            'type' => 'charge.failed',
            'data' => [
                'object' => 'charge',
                'id' => 'chrg_test_123456798',
                'status' => 'failed',
                'amount' => 50000,
                'currency' => 'jpy',
                'metadata' => [
                    'invoice_id' => (string)$invoice->id,
                    'user_id' => (string)$student->id
                ]
            ]
        ];

        $response = $this->postJson('/api/payment/webhook', $webhookData);

        $response->assertStatus(200);
        
        // 验证学生没有注册到课程
        $this->assertDatabaseMissing('course_student', [
            'student_id' => $student->id,
            'course_id' => $course->id
        ]);
    }

    /** @test */
    public function test_duplicate_enrollment_is_prevented()
    {
        // 创建测试数据
        $student = $this->createStudent();
        $teacher = $this->createTeacher();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);
        $invoice = $this->createInvoice([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'amount' => 500.00,
            'status' => 0 // 待支付
        ]);

        // 手动插入一个已存在的关联记录
        DB::table('course_student')->insert([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // 模拟支付成功的 webhook
        $webhookData = [
            'object' => 'event',
            'id' => 'evnt_test_123456799',
            'type' => 'charge.complete',
            'data' => [
                'object' => 'charge',
                'id' => 'chrg_test_123456799',
                'status' => 'successful',
                'amount' => 50000,
                'currency' => 'jpy',
                'metadata' => [
                    'invoice_id' => (string)$invoice->id,
                    'user_id' => (string)$student->id
                ]
            ]
        ];

        $response = $this->postJson('/api/payment/webhook', $webhookData);

        $response->assertStatus(200);
        
        // 验证只有一个关联记录
        $count = DB::table('course_student')
            ->where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->count();
        
        $this->assertEquals(1, $count);
    }

    /** @test */
    public function test_student_can_view_enrolled_courses()
    {
        // 创建测试数据
        $student = $this->createStudent();
        $teacher = $this->createTeacher();
        $course1 = $this->createCourse(['teacher_id' => $teacher->id]);
        $course2 = $this->createCourse(['teacher_id' => $teacher->id]);

        // 手动插入课程关联
        DB::table('course_student')->insert([
            'student_id' => $student->id,
            'course_id' => $course1->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $response = $this->actingAsUser($student)
            ->getJson('/api/students/my-courses');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'year_month',
                        'fee',
                        'teacher' => [
                            'id',
                            'name'
                        ]
                    ]
                ]
            ]
        ]);

        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals($course1->id, $data[0]['id']);
    }

    /** @test */
    public function test_student_cannot_view_unenrolled_courses()
    {
        // 创建测试数据
        $student = $this->createStudent();
        $teacher = $this->createTeacher();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);

        // 不创建课程关联

        $response = $this->actingAsUser($student)
            ->getJson('/api/students/my-courses');

        $response->assertStatus(200);
        
        $data = $response->json('data.data');
        $this->assertCount(0, $data);
    }

    /** @test */
    public function test_course_student_table_has_correct_structure()
    {
        // 创建测试数据
        $student = $this->createStudent();
        $teacher = $this->createTeacher();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);

        // 插入课程关联
        DB::table('course_student')->insert([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // 验证表结构
        $record = DB::table('course_student')
            ->where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->first();

        $this->assertNotNull($record);
        $this->assertNotNull($record->created_at);
        $this->assertNotNull($record->updated_at);
        
        // 验证没有 status 字段（已被移除）
        $this->assertObjectNotHasProperty('status', $record);
    }
}
