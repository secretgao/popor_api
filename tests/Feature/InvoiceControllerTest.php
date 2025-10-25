<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Course;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_teacher_can_get_invoices_list()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);

        // 创建一些账单
        $invoices = collect([
            $this->createInvoice([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'teacher_id' => $teacher->id,
                'amount' => 500.00
            ]),
            $this->createInvoice([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'teacher_id' => $teacher->id,
                'amount' => 600.00
            ])
        ]);

        $response = $this->actingAsUser($teacher)
            ->getJson('/api/invoices');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'data' => [
                    '*' => [
                        'id',
                        'student_id',
                        'course_id',
                        'teacher_id',
                        'amount',
                        'year_month',
                        'status',
                        'sent_at',
                        'paid_at',
                        'student' => [
                            'id',
                            'name',
                            'username'
                        ],
                        'course' => [
                            'id',
                            'name'
                        ],
                        'teacher' => [
                            'id',
                            'name'
                        ],
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
    public function test_teacher_can_create_invoice()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);

        $invoiceData = [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'amount' => 500.00,
            'year_month' => '2024-01'
        ];

        $response = $this->actingAsUser($teacher)
            ->postJson('/api/invoices', $invoiceData);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);

        // 验证账单已创建
        $this->assertDatabaseHas('invoices', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'amount' => 500.00,
            'year_month' => '2024-01',
            'status' => 'pending'
        ]);
    }

    /** @test */
    public function test_teacher_can_get_invoice_details()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);
        $invoice = $this->createInvoice([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id
        ]);

        $response = $this->actingAsUser($teacher)
            ->getJson("/api/invoices/{$invoice->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'student_id',
                'course_id',
                'teacher_id',
                'amount',
                'year_month',
                'status',
                'student' => [
                    'id',
                    'name',
                    'username'
                ],
                'course' => [
                    'id',
                    'name'
                ],
                'teacher' => [
                    'id',
                    'name'
                ],
                'created_at',
                'updated_at'
            ]
        ]);
    }

    /** @test */
    public function test_teacher_can_update_invoice()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);
        $invoice = $this->createInvoice([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id
        ]);

        $updateData = [
            'amount' => 750.00,
            'year_month' => '2024-02'
        ];

        $response = $this->actingAsUser($teacher)
            ->putJson("/api/invoices/{$invoice->id}", $updateData);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // 验证更新
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'amount' => 750.00,
            'year_month' => '2024-02'
        ]);
    }

    /** @test */
    public function test_teacher_can_delete_invoice()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);
        $invoice = $this->createInvoice([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id
        ]);

        $response = $this->actingAsUser($teacher)
            ->deleteJson("/api/invoices/{$invoice->id}");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // 验证账单已删除
        $this->assertDatabaseMissing('invoices', [
            'id' => $invoice->id
        ]);
    }

    /** @test */
    public function test_student_can_view_own_invoices()
    {
        $student = $this->createStudent();
        $teacher = $this->createTeacher();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);

        $invoice = $this->createInvoice([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id
        ]);

        $response = $this->actingAsUser($student)
            ->getJson('/api/invoices');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
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
                        'teacher' => [
                            'id',
                            'name'
                        ],
                        'created_at'
                    ]
                ]
            ]
        ]);
    }

    /** @test */
    public function test_invoice_creation_requires_valid_data()
    {
        $teacher = $this->createTeacher();

        $response = $this->actingAsUser($teacher)
            ->postJson('/api/invoices', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['student_id', 'course_id', 'amount', 'year_month']);
    }

    /** @test */
    public function test_invoice_amount_must_be_numeric()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);

        $response = $this->actingAsUser($teacher)
            ->postJson('/api/invoices', [
                'student_id' => $student->id,
                'course_id' => $course->id,
                'amount' => 'invalid_amount',
                'year_month' => '2024-01'
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function test_year_month_must_be_valid_format()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);

        $response = $this->actingAsUser($teacher)
            ->postJson('/api/invoices', [
                'student_id' => $student->id,
                'course_id' => $course->id,
                'amount' => 500.00,
                'year_month' => 'invalid_date'
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['year_month']);
    }

    /** @test */
    public function test_cannot_create_duplicate_invoice()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);

        // 创建第一个账单
        $this->createInvoice([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'year_month' => '2024-01'
        ]);

        // 尝试创建重复账单
        $response = $this->actingAsUser($teacher)
            ->postJson('/api/invoices', [
                'student_id' => $student->id,
                'course_id' => $course->id,
                'amount' => 500.00,
                'year_month' => '2024-01'
            ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    /** @test */
    public function test_teacher_cannot_access_other_teacher_invoices()
    {
        $teacher1 = $this->createTeacher();
        $teacher2 = $this->createTeacher();
        $student = $this->createStudent();
        $course = $this->createCourse(['teacher_id' => $teacher2->id]);
        $invoice = $this->createInvoice([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher2->id
        ]);

        $response = $this->actingAsUser($teacher1)
            ->getJson("/api/invoices/{$invoice->id}");

        $response->assertStatus(403);
        $response->assertJson(['success' => false]);
    }

    /** @test */
    public function test_invoice_pagination_works()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);

        // 创建多个账单
        for ($i = 1; $i <= 15; $i++) {
            $this->createInvoice([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'teacher_id' => $teacher->id,
                'amount' => 500.00 + $i
            ]);
        }

        $response = $this->actingAsUser($teacher)
            ->getJson('/api/invoices?page=1&per_page=10');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertEquals(1, $data['current_page']);
        $this->assertEquals(10, $data['per_page']);
        $this->assertEquals(15, $data['total']);
        $this->assertCount(10, $data['data']);
    }

    /** @test */
    public function test_invoice_status_filtering()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);

        // 创建不同状态的账单
        $this->createInvoice([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'status' => 'pending'
        ]);

        $this->createInvoice([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'status' => 'paid'
        ]);

        $response = $this->actingAsUser($teacher)
            ->getJson('/api/invoices?status=pending');

        $response->assertStatus(200);
        
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('pending', $data[0]['status']);
    }

    /** @test */
    public function test_unauthenticated_user_cannot_access_invoices()
    {
        $response = $this->getJson('/api/invoices');

        $response->assertStatus(401);
        $response->assertJson(['success' => false]);
    }

    /** @test */
    public function test_invoice_amount_can_be_updated()
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
            ->putJson("/api/invoices/{$invoice->id}", [
                'amount' => 750.00
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'amount' => 750.00
        ]);
    }
}
