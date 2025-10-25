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
    public function test_can_create_payment_token()
    {
        $tokenData = [
            'card' => [
                'name' => 'Test User',
                'number' => '4242424242424242',
                'expiration_month' => 12,
                'expiration_year' => 2025,
                'security_code' => '123'
            ]
        ];

        $response = $this->postJson('/api/payment/create-token', $tokenData);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'token_id',
                'card' => [
                    'last_digits',
                    'brand',
                    'expiration_month',
                    'expiration_year'
                ]
            ]
        ]);
    }

    /** @test */
    public function test_create_token_requires_card_data()
    {
        $response = $this->postJson('/api/payment/create-token', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['card']);
    }

    /** @test */
    public function test_create_token_requires_valid_card_number()
    {
        $tokenData = [
            'card' => [
                'name' => 'Test User',
                'number' => 'invalid_card_number',
                'expiration_month' => 12,
                'expiration_year' => 2025,
                'security_code' => '123'
            ]
        ];

        $response = $this->postJson('/api/payment/create-token', $tokenData);

        $response->assertStatus(400);
        $response->assertJson(['success' => false]);
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
            'amount' => 500.00
        ]);

        $paymentData = [
            'invoice_id' => $invoice->id,
            'token_id' => 'tokn_test_123456789',
            'amount' => 500.00
        ];

        $response = $this->actingAsUser($student)
            ->postJson('/api/payment/process', $paymentData);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'charge_id',
                'status',
                'amount',
                'currency'
            ]
        ]);
    }

    /** @test */
    public function test_payment_processing_requires_authentication()
    {
        $paymentData = [
            'invoice_id' => 1,
            'token_id' => 'tokn_test_123456789',
            'amount' => 500.00
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
            'status' => 'paid'
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
            'status' => 'paid'
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
            'status' => 'pending' // 未支付状态
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
            'type' => 'charge.complete',
            'data' => [
                'id' => 'chrg_test_123456789',
                'status' => 'successful',
                'amount' => 50000,
                'currency' => 'thb'
            ]
        ];

        $response = $this->postJson('/api/payment/webhook', $webhookData);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /** @test */
    public function test_payment_webhook_handles_failure()
    {
        $webhookData = [
            'type' => 'charge.failed',
            'data' => [
                'id' => 'chrg_test_123456789',
                'status' => 'failed',
                'amount' => 50000,
                'currency' => 'thb'
            ]
        ];

        $response = $this->postJson('/api/payment/webhook', $webhookData);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /** @test */
    public function test_payment_webhook_requires_valid_data()
    {
        $response = $this->postJson('/api/payment/webhook', []);

        $response->assertStatus(400);
        $response->assertJson(['success' => false]);
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
            'status' => 'paid' // 已支付状态
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
