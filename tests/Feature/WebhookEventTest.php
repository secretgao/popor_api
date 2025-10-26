<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Course;
use App\Models\Invoice;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WebhookEventTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_webhook_event_is_created_on_webhook_call()
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
                'currency' => 'jpy'
            ]
        ];

        $response = $this->postJson('/api/payment/webhook', $webhookData);

        $response->assertStatus(200);
        
        // 验证 webhook 事件已创建
        $this->assertDatabaseHas('webhook_events', [
            'event_id' => 'evnt_test_123456789',
            'type' => 'charge.complete',
            'process_status' => 1
        ]);
    }

    /** @test */
    public function test_webhook_event_handles_direct_charge_object()
    {
        $webhookData = [
            'object' => 'charge',
            'id' => 'chrg_test_123456790',
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
        
        // 验证 webhook 事件已创建（type 应该被推断为 charge.complete）
        $this->assertDatabaseHas('webhook_events', [
            'event_id' => null, // 直接 charge 对象没有 event_id
            'type' => 'charge.complete',
            'process_status' => 1
        ]);
    }

    /** @test */
    public function test_webhook_event_prevents_duplicate_processing()
    {
        // 创建已存在的 webhook 事件
        WebhookEvent::create([
            'event_id' => 'evnt_test_123456791',
            'type' => 'charge.complete',
            'payload' => ['test' => 'data'],
            'process_status' => 1
        ]);

        $webhookData = [
            'object' => 'event',
            'id' => 'evnt_test_123456791',
            'type' => 'charge.complete',
            'data' => [
                'object' => 'charge',
                'id' => 'chrg_test_123456791',
                'status' => 'successful',
                'amount' => 50000,
                'currency' => 'jpy'
            ]
        ];

        $response = $this->postJson('/api/payment/webhook', $webhookData);

        $response->assertStatus(200);
        
        // 验证只有一个 webhook 事件记录
        $this->assertEquals(1, WebhookEvent::where('event_id', 'evnt_test_123456791')->count());
    }

    /** @test */
    public function test_webhook_event_handles_failed_charge()
    {
        $webhookData = [
            'object' => 'event',
            'id' => 'evnt_test_123456792',
            'type' => 'charge.failed',
            'data' => [
                'object' => 'charge',
                'id' => 'chrg_test_123456792',
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
        
        // 验证 webhook 事件已创建
        $this->assertDatabaseHas('webhook_events', [
            'event_id' => 'evnt_test_123456792',
            'type' => 'charge.failed',
            'process_status' => 1
        ]);
    }

    /** @test */
    public function test_webhook_event_stores_payload_correctly()
    {
        $webhookData = [
            'object' => 'event',
            'id' => 'evnt_test_123456793',
            'type' => 'charge.complete',
            'data' => [
                'object' => 'charge',
                'id' => 'chrg_test_123456793',
                'status' => 'successful',
                'amount' => 50000,
                'currency' => 'jpy'
            ]
        ];

        $response = $this->postJson('/api/payment/webhook', $webhookData);

        $response->assertStatus(200);
        
        // 验证 payload 已正确存储
        $webhookEvent = WebhookEvent::where('event_id', 'evnt_test_123456793')->first();
        $this->assertNotNull($webhookEvent);
        $this->assertEquals($webhookData, $webhookEvent->payload);
    }

    /** @test */
    public function test_webhook_event_handles_missing_signature_in_test_mode()
    {
        // 在测试模式下，即使没有签名也应该成功
        $webhookData = [
            'object' => 'charge',
            'id' => 'chrg_test_123456794',
            'status' => 'successful',
            'amount' => 50000,
            'currency' => 'jpy'
        ];

        $response = $this->postJson('/api/payment/webhook', $webhookData);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }

    /** @test */
    public function test_webhook_event_updates_invoice_status_on_success()
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

        $webhookData = [
            'object' => 'event',
            'id' => 'evnt_test_123456795',
            'type' => 'charge.complete',
            'data' => [
                'object' => 'charge',
                'id' => 'chrg_test_123456795',
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
        
        // 验证账单状态已更新
        $invoice->refresh();
        $this->assertEquals(2, $invoice->status); // 支付成功
        $this->assertNotNull($invoice->paid_at);
    }

    /** @test */
    public function test_webhook_event_updates_invoice_status_on_failure()
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

        $webhookData = [
            'object' => 'event',
            'id' => 'evnt_test_123456796',
            'type' => 'charge.failed',
            'data' => [
                'object' => 'charge',
                'id' => 'chrg_test_123456796',
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
        
        // 验证账单状态已更新
        $invoice->refresh();
        $this->assertEquals(3, $invoice->status); // 支付失败
    }
}
