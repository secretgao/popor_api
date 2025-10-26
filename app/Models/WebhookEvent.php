<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'type',
        'payload',
        'process_status',
        'event_created_at',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'event_created_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    // 处理状态常量
    const STATUS_PENDING = 0;    // 未处理
    const STATUS_PROCESSED = 1;  // 已处理
    const STATUS_FAILED = 2;     // 处理失败

    /**
     * 检查事件是否已处理
     */
    public function isProcessed(): bool
    {
        return $this->process_status === self::STATUS_PROCESSED;
    }

    /**
     * 检查事件是否处理失败
     */
    public function isFailed(): bool
    {
        return $this->process_status === self::STATUS_FAILED;
    }

    /**
     * 检查事件是否待处理
     */
    public function isPending(): bool
    {
        return $this->process_status === self::STATUS_PENDING;
    }

    /**
     * 标记为已处理
     */
    public function markAsProcessed(): void
    {
        $this->update([
            'process_status' => self::STATUS_PROCESSED,
            'processed_at' => now(),
        ]);
    }

    /**
     * 标记为处理失败
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'process_status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'processed_at' => now(),
        ]);
    }

    /**
     * 根据事件ID查找或创建事件
     */
    public static function findOrCreateByEventId(string $eventId, array $data): self
    {
        return self::firstOrCreate(
            ['event_id' => $eventId],
            $data
        );
    }
}
