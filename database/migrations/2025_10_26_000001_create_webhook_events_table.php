<?php

// database/migrations/2025_10_26_000001_create_webhook_events_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();

            // Omise 事件唯一ID（如 evnt_test_...），用于幂等检查
            $table->string('event_id')->unique()->comment('Omise 事件唯一ID，用于幂等');

            // 事件类型（如 charge.complete / charge.failed）
            $table->string('type')->comment('事件类型，如 charge.complete');

            // 原始事件载荷（JSON）
            $table->json('payload')->comment('原始事件 JSON 载荷');

            // 处理状态：0=未处理，1=已处理，2=处理失败（保留扩展）
            $table->unsignedInteger('process_status')->default(1)->comment('处理状态：0未处理，1已处理，2处理失败');

            // 事件创建时间（从 Omise payload.created，可选）
            $table->timestamp('event_created_at')->nullable()->comment('Omise 事件创建时间');

            // 实际处理完成时间
            $table->timestamp('processed_at')->nullable()->comment('处理完成时间');

            // 处理错误信息（当 process_status=2 时存储）
            $table->text('error_message')->nullable()->comment('处理错误信息');

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('webhook_events');
    }
};
