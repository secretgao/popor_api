<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'course_id',
        'teacher_id',
        'amount',
        'year_month',
        'status',
        'sent_at',
        'paid_at',
        'payment_method',
        'currency',
        'omise_charge_id',
        'omise_source_id',
        'omise_last_event_id'
    ];

    protected $casts = [
        'sent_at' => 'datetime:Y-m-d H:i:s',
        'paid_at' => 'datetime:Y-m-d H:i:s',
        'amount' => 'decimal:2',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s'
    ];

    /**
     * 时间格式
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * 获取账单对应的学生
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * 获取账单对应的课程
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    /**
     * 获取账单对应的教师
     */
    public function teacher(): BelongsTo
    {
        // 教师信息从admin_users表获取
        return $this->belongsTo(\App\Models\AdminUser::class, 'teacher_id');
    }

    /**
     * 获取状态名称
     */
    public function getStatusNameAttribute(): string
    {
        return match($this->status) {
            0 => '待支付',
            1 => '已支付',
            2 => '已过期',
            default => '未知'
        };
    }

    /**
     * 获取格式化的创建时间
     */
    public function getFormattedCreatedAtAttribute()
    {
        return $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null;
    }

    /**
     * 获取格式化的更新时间
     */
    public function getFormattedUpdatedAtAttribute()
    {
        return $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null;
    }

    /**
     * 获取格式化的发送时间
     */
    public function getFormattedSentAtAttribute()
    {
        return $this->sent_at ? $this->sent_at->format('Y-m-d H:i:s') : null;
    }

    /**
     * 获取格式化的支付时间
     */
    public function getFormattedPaidAtAttribute()
    {
        return $this->paid_at ? $this->paid_at->format('Y-m-d H:i:s') : null;
    }
}
