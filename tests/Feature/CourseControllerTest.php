<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CourseControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_teacher_can_get_courses_list()
    {
        $teacher = $this->createTeacher();
        
        // 创建一些课程
        $courses = collect([
            $this->createCourse(['teacher_id' => $teacher->id, 'name' => 'Course 1']),
            $this->createCourse(['teacher_id' => $teacher->id, 'name' => 'Course 2']),
            $this->createCourse(['teacher_id' => $teacher->id, 'name' => 'Course 3'])
        ]);

        $response = $this->actingAsUser($teacher)
            ->getJson('/api/courses');

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
                        'teacher_id',
                        'teacher_name',
                        'is_del',
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
    public function test_teacher_can_create_course()
    {
        $teacher = $this->createTeacher();

        $courseData = [
            'name' => 'New Course',
            'year_month' => '2024-01',
            'fee' => 500.00
        ];

        $response = $this->actingAsUser($teacher)
            ->postJson('/api/courses', $courseData);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);

        // 验证课程已创建
        $this->assertDatabaseHas('courses', [
            'name' => 'New Course',
            'year_month' => '2024-01',
            'fee' => 500.00,
            'teacher_id' => $teacher->id,
            'is_del' => false
        ]);
    }

    /** @test */
    public function test_teacher_can_update_course()
    {
        $teacher = $this->createTeacher();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);

        $updateData = [
            'name' => 'Updated Course Name',
            'year_month' => '2024-02',
            'fee' => 600.00
        ];

        $response = $this->actingAsUser($teacher)
            ->putJson("/api/courses/{$course->id}", $updateData);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // 验证更新
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'name' => 'Updated Course Name',
            'year_month' => '2024-02',
            'fee' => 600.00
        ]);
    }

    /** @test */
    public function test_teacher_can_update_course_status()
    {
        $teacher = $this->createTeacher();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);

        $response = $this->actingAsUser($teacher)
            ->putJson("/api/courses/{$course->id}/status", [
                'is_del' => true
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // 验证状态更新
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'is_del' => true
        ]);
    }

    /** @test */
    public function test_student_can_view_courses()
    {
        $student = $this->createStudent();
        $teacher = $this->createTeacher();
        
        // 创建一些课程
        $this->createCourse(['teacher_id' => $teacher->id]);
        $this->createCourse(['teacher_id' => $teacher->id]);

        $response = $this->actingAsUser($student)
            ->getJson('/api/courses');

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
                        'teacher_name',
                        'created_at'
                    ]
                ]
            ]
        ]);
    }

    /** @test */
    public function test_course_creation_requires_valid_data()
    {
        $teacher = $this->createTeacher();

        $response = $this->actingAsUser($teacher)
            ->postJson('/api/courses', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'year_month', 'fee']);
    }

    /** @test */
    public function test_course_fee_must_be_numeric()
    {
        $teacher = $this->createTeacher();

        $response = $this->actingAsUser($teacher)
            ->postJson('/api/courses', [
                'name' => 'Test Course',
                'year_month' => '2024-01',
                'fee' => 'invalid_fee'
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['fee']);
    }

    /** @test */
    public function test_year_month_must_be_valid_format()
    {
        $teacher = $this->createTeacher();

        $response = $this->actingAsUser($teacher)
            ->postJson('/api/courses', [
                'name' => 'Test Course',
                'year_month' => 'invalid_date',
                'fee' => 500.00
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['year_month']);
    }

    /** @test */
    public function test_course_name_is_required()
    {
        $teacher = $this->createTeacher();

        $response = $this->actingAsUser($teacher)
            ->postJson('/api/courses', [
                'year_month' => '2024-01',
                'fee' => 500.00
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function test_unauthenticated_user_cannot_access_courses()
    {
        $response = $this->getJson('/api/courses');

        $response->assertStatus(401);
        $response->assertJson(['success' => false]);
    }

    /** @test */
    public function test_teacher_cannot_update_other_teacher_course()
    {
        $teacher1 = $this->createTeacher();
        $teacher2 = $this->createTeacher();
        $course = $this->createCourse(['teacher_id' => $teacher2->id]);

        $response = $this->actingAsUser($teacher1)
            ->putJson("/api/courses/{$course->id}", [
                'name' => 'Unauthorized Update'
            ]);

        $response->assertStatus(403);
        $response->assertJson(['success' => false]);
    }

    /** @test */
    public function test_course_pagination_works()
    {
        $teacher = $this->createTeacher();

        // 创建多个课程
        for ($i = 1; $i <= 15; $i++) {
            $this->createCourse([
                'teacher_id' => $teacher->id,
                'name' => "Course {$i}"
            ]);
        }

        $response = $this->actingAsUser($teacher)
            ->getJson('/api/courses?page=1&per_page=10');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertEquals(1, $data['current_page']);
        $this->assertEquals(10, $data['per_page']);
        $this->assertEquals(15, $data['total']);
        $this->assertCount(10, $data['data']);
    }

    /** @test */
    public function test_course_search_by_name()
    {
        $teacher = $this->createTeacher();
        
        $this->createCourse(['teacher_id' => $teacher->id, 'name' => 'Math Course']);
        $this->createCourse(['teacher_id' => $teacher->id, 'name' => 'Science Course']);
        $this->createCourse(['teacher_id' => $teacher->id, 'name' => 'English Course']);

        $response = $this->actingAsUser($teacher)
            ->getJson('/api/courses?search=Math');

        $response->assertStatus(200);
        
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('Math Course', $data[0]['name']);
    }

    /** @test */
    public function test_course_filter_by_teacher()
    {
        $teacher1 = $this->createTeacher();
        $teacher2 = $this->createTeacher();
        
        $this->createCourse(['teacher_id' => $teacher1->id, 'name' => 'Teacher1 Course']);
        $this->createCourse(['teacher_id' => $teacher2->id, 'name' => 'Teacher2 Course']);

        $response = $this->actingAsUser($teacher1)
            ->getJson("/api/courses?teacher_id={$teacher1->id}");

        $response->assertStatus(200);
        
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('Teacher1 Course', $data[0]['name']);
    }

    /** @test */
    public function test_course_soft_delete_status()
    {
        $teacher = $this->createTeacher();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);

        // 软删除课程
        $response = $this->actingAsUser($teacher)
            ->putJson("/api/courses/{$course->id}/status", [
                'is_del' => true
            ]);

        $response->assertStatus(200);

        // 验证课程仍然存在于数据库中，但标记为已删除
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'is_del' => true
        ]);

        // 恢复课程
        $response = $this->actingAsUser($teacher)
            ->putJson("/api/courses/{$course->id}/status", [
                'is_del' => false
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'is_del' => false
        ]);
    }
}
