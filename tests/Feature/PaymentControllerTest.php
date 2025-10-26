<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Course;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_can_get_payment_config()
    {
        $response = $this->getJson('/api/payment/config');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'omise_public_key',
                'currency',
                'supported_cards'
            ]
        ]);

        $response->assertJson(['success' => true]);
    }

    /** @test */
    public function test_payment_config_returns_correct_structure()
    {
        $response = $this->getJson('/api/payment/config');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'omise_public_key',
                'currency',
                'supported_cards'
            ]
        ]);

        $response->assertJson(['success' => true]);
        
        // 验证返回的数据
        $data = $response->json('data');
        $this->assertNotEmpty($data['omise_public_key']);
        $this->assertEquals('JPY', $data['currency']);
        $this->assertIsArray($data['supported_cards']);
    }

    /** @test */
    public function test_authenticated_user_can_process_payment()
    {
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

        $paymentData = [
            'token' => 'tokn_test_123456789',
            'amount' => 500,
            'currency' => 'JPY',
            'description' => '课程费用 - 测试课程',
            'metadata' => [
                'invoice_id' => $invoice->id,
                'user_id' => $student->id
            ]
        ];

        $response = $this->actingAsUser($student)
            ->postJson('/api/payment/process', $paymentData);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'charge_id',
            'status',
            'amount',
            'currency',
            'transaction_id'
        ]);
    }

    /** @test */
    public function test_payment_processing_requires_authentication()
    {
        $paymentData = [
            'token' => 'tokn_test_123456789',
            'amount' => 500,
            'currency' => 'JPY',
            'description' => '测试支付'
        ];

        $response = $this->postJson('/api/payment/process', $paymentData);

        $response->assertStatus(401);
        $response->assertJson(['success' => false]);
    }

    /** @test */
    public function test_payment_processing_requires_valid_invoice()
    {
        $student = $this->createStudent();

        $paymentData = [
            'invoice_id' => 999999, // 不存在的账单ID
            'token_id' => 'tokn_test_123456789',
            'amount' => 500.00
        ];

        $response = $this->actingAsUser($student)
            ->postJson('/api/payment/process', $paymentData);

        $response->assertStatus(404);
        $response->assertJson(['success' => false]);
    }

    /** @test */
    public function test_can_get_charge_details()
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

        $chargeId = 'chrg_test_123456789';

        $response = $this->actingAsUser($student)
            ->getJson("/api/payment/charge/{$chargeId}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'status',
                'amount',
                'currency',
                'created_at'
            ]
        ]);
    }

    /** @test */
    public function test_can_process_refund()
    {
        $student = $this->createStudent();
        $teacher = $this->createTeacher();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);
        $invoice = $this->createInvoice([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'amount' => 500.00,
            'status' => 2 // 支付成功
        ]);

        $refundData = [
            'invoice_id' => $invoice->id,
            'amount' => 500.00,
            'reason' => 'Student request'
        ];

        $response = $this->actingAsUser($teacher)
            ->postJson('/api/payment/refund', $refundData);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'refund_id',
                'amount',
                'status'
            ]
        ]);
    }

    /** @test */
    public function test_refund_requires_teacher_authentication()
    {
        $student = $this->createStudent();
        $teacher = $this->createTeacher();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);
        $invoice = $this->createInvoice([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'amount' => 500.00,
            'status' => 2 // 支付成功
        ]);

        $refundData = [
            'invoice_id' => $invoice->id,
            'amount' => 500.00,
            'reason' => 'Student request'
        ];

        // 学生尝试退款（应该失败）
        $response = $this->actingAsUser($student)
            ->postJson('/api/payment/refund', $refundData);

        $response->assertStatus(403);
        $response->assertJson(['success' => false]);
    }

    /** @test */
    public function test_cannot_refund_unpaid_invoice()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);
        $invoice = $this->createInvoice([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'amount' => 500.00,
            'status' => 0 // 待支付 // 未支付状态
        ]);

        $refundData = [
            'invoice_id' => $invoice->id,
            'amount' => 500.00,
            'reason' => 'Student request'
        ];

        $response = $this->actingAsUser($teacher)
            ->postJson('/api/payment/refund', $refundData);

        $response->assertStatus(400);
        $response->assertJson(['success' => false]);
    }

    /** @test */
    public function test_payment_webhook_handles_success()
    {
        $webhookData = [
            'object' => 'event',
            'id' => 'evnt_test_123456789',
            'type' => 'charge.complete',
            'data' => [
                'object' => 'charge',
                'id' => 'chrg_test_123456789',
                'status' => 'successful',
                'amount' => 50000,
                'currency' => 'jpy',
                'metadata' => [
                    'invoice_id' => '1',
                    'user_id' => '1'
                ]
            ]
        ];

        $response = $this->postJson('/api/payment/webhook', $webhookData);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }

    /** @test */
    public function test_payment_webhook_handles_failure()
    {
        $webhookData = [
            'object' => 'event',
            'id' => 'evnt_test_123456790',
            'type' => 'charge.failed',
            'data' => [
                'object' => 'charge',
                'id' => 'chrg_test_123456790',
                'status' => 'failed',
                'amount' => 50000,
                'currency' => 'jpy',
                'metadata' => [
                    'invoice_id' => '1',
                    'user_id' => '1'
                ]
            ]
        ];

        $response = $this->postJson('/api/payment/webhook', $webhookData);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }

    /** @test */
    public function test_payment_webhook_handles_direct_charge()
    {
        // 测试直接 charge 对象（没有 event 包装）
        $webhookData = [
            'object' => 'charge',
            'id' => 'chrg_test_123456791',
            'status' => 'successful',
            'amount' => 50000,
            'currency' => 'jpy',
            'metadata' => [
                'invoice_id' => '1',
                'user_id' => '1'
            ]
        ];

        $response = $this->postJson('/api/payment/webhook', $webhookData);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }

    /** @test */
    public function test_payment_webhook_requires_valid_data()
    {
        $response = $this->postJson('/api/payment/webhook', []);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Invalid signature']);
    }

    /** @test */
    public function test_payment_amount_must_match_invoice()
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

        $paymentData = [
            'invoice_id' => $invoice->id,
            'token_id' => 'tokn_test_123456789',
            'amount' => 600.00 // 错误的金额
        ];

        $response = $this->actingAsUser($student)
            ->postJson('/api/payment/process', $paymentData);

        $response->assertStatus(400);
        $response->assertJson(['success' => false]);
    }

    /** @test */
    public function test_cannot_pay_already_paid_invoice()
    {
        $student = $this->createStudent();
        $teacher = $this->createTeacher();
        $course = $this->createCourse(['teacher_id' => $teacher->id]);
        $invoice = $this->createInvoice([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'amount' => 500.00,
            'status' => 2 // 支付成功 // 已支付状态
        ]);

        $paymentData = [
            'invoice_id' => $invoice->id,
            'token_id' => 'tokn_test_123456789',
            'amount' => 500.00
        ];

        $response = $this->actingAsUser($student)
            ->postJson('/api/payment/process', $paymentData);

        $response->assertStatus(400);
        $response->assertJson(['success' => false]);
    }
}
