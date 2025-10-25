<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Course;
use App\Models\Invoice;
use App\Models\CourseStudent;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StudentControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_teacher_can_get_students_list()
    {
        $teacher = $this->createTeacher();
        
        // 创建一些学生
        $students = collect([
            $this->createStudent(['username' => 'student1']),
            $this->createStudent(['username' => 'student2']),
            $this->createStudent(['username' => 'student3'])
        ]);

        $response = $this->actingAsUser($teacher)
            ->getJson('/api/students');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'data' => [
                    '*' => [
                        'id',
                        'username',
                        'name',
                        'email',
                        'role',
                        'is_active',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'current_page',
                'per_page',
                'total'
            ]
        ]);

        $response->assertJson(['success' => true]);
    }

    /** @test */
    public function test_teacher_can_create_student()
    {
        $teacher = $this->createTeacher();

        $studentData = [
            'username' => 'newstudent',
            'name' => 'New Student',
            'email' => 'newstudent@example.com',
            'password' => 'password123'
        ];

        $response = $this->actingAsUser($teacher)
            ->postJson('/api/students', $studentData);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);

        // 验证学生已创建
        $this->assertDatabaseHas('users', [
            'username' => 'newstudent',
            'name' => 'New Student',
            'email' => 'newstudent@example.com',
            'role' => 0 // 学生角色
        ]);
    }

    /** @test */
    public function test_teacher_can_get_student_details()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();

        $response = $this->actingAsUser($teacher)
            ->getJson("/api/students/{$student->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'username',
                'name',
                'email',
                'role',
                'is_active',
                'created_at',
                'updated_at'
            ]
        ]);
    }

    /** @test */
    public function test_teacher_can_update_student()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();

        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com'
        ];

        $response = $this->actingAsUser($teacher)
            ->putJson("/api/students/{$student->id}", $updateData);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // 验证更新
        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com'
        ]);
    }

    /** @test */
    public function test_teacher_can_update_student_status()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();

        $response = $this->actingAsUser($teacher)
            ->putJson("/api/students/{$student->id}/status", [
                'is_active' => false
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // 验证状态更新
        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'is_active' => false
        ]);
    }

    /** @test */
    public function test_student_can_get_own_courses()
    {
        $student = $this->createStudent();
        $course = $this->createCourse();

        // 创建选课记录
        CourseStudent::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active'
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
                        'course' => [
                            'id',
                            'name',
                            'year_month',
                            'fee',
                            'teacher'
                        ],
                        'status',
                        'created_at'
                    ]
                ]
            ]
        ]);
    }

    /** @test */
    public function test_teacher_can_get_student_courses()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);

        // 创建选课记录
        CourseStudent::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active'
        ]);

        $response = $this->actingAsUser($teacher)
            ->getJson("/api/students/{$student->id}/courses");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'course' => [
                        'id',
                        'name',
                        'year_month',
                        'fee'
                    ],
                    'status',
                    'created_at'
                ]
            ]
        ]);
    }

    /** @test */
    public function test_teacher_can_get_student_invoices()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);

        // 创建账单
        $invoice = $this->createInvoice([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id
        ]);

        $response = $this->actingAsUser($teacher)
            ->getJson("/api/students/{$student->id}/invoices");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'amount',
                    'year_month',
                    'status',
                    'course' => [
                        'id',
                        'name'
                    ],
                    'created_at'
                ]
            ]
        ]);
    }

    /** @test */
    public function test_unauthenticated_user_cannot_access_students()
    {
        $response = $this->getJson('/api/students');

        $response->assertStatus(401);
        $response->assertJson(['success' => false]);
    }

    /** @test */
    public function test_student_cannot_access_other_students()
    {
        $student1 = $this->createStudent();
        $student2 = $this->createStudent();

        $response = $this->actingAsUser($student1)
            ->getJson("/api/students/{$student2->id}");

        $response->assertStatus(403);
        $response->assertJson(['success' => false]);
    }

    /** @test */
    public function test_create_student_requires_valid_data()
    {
        $teacher = $this->createTeacher();

        $response = $this->actingAsUser($teacher)
            ->postJson('/api/students', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['username', 'name', 'email', 'password']);
    }

    /** @test */
    public function test_cannot_create_student_with_duplicate_username()
    {
        $teacher = $this->createTeacher();
        $existingStudent = $this->createStudent(['username' => 'existing']);

        $response = $this->actingAsUser($teacher)
            ->postJson('/api/students', [
                'username' => 'existing',
                'name' => 'New Student',
                'email' => 'new@example.com',
                'password' => 'password123'
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['username']);
    }

    /** @test */
    public function test_pagination_works_for_students_list()
    {
        $teacher = $this->createTeacher();

        // 创建多个学生
        for ($i = 1; $i <= 15; $i++) {
            $this->createStudent(['username' => "student{$i}"]);
        }

        $response = $this->actingAsUser($teacher)
            ->getJson('/api/students?page=1&per_page=10');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertEquals(1, $data['current_page']);
        $this->assertEquals(10, $data['per_page']);
        $this->assertEquals(15, $data['total']);
        $this->assertCount(10, $data['data']);
    }
}
