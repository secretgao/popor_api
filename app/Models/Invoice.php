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
        'paid_at',
        'currency',
        'omise_charge_id',
        'payment_success',
        'payment_status',
        'payment_transaction_id',
        'payment_error_message',
        'payment_processed_at'
    ];

    protected $casts = [
        'paid_at' => 'datetime:Y-m-d H:i:s',
        'amount' => 'decimal:2',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s'
    ];

    // 数字状态常量
    const STATUS_PENDING = 0;      // 待支付
    const STATUS_PROCESSING = 1;   // 支付中
    const STATUS_PAID = 2;         // 支付成功
    const STATUS_FAILED = 3;       // 支付失败
    const STATUS_REFUNDED = 4;     // 已退款


    public static  $status_type = [
            self::STATUS_PENDING=>'待支付',
            self::STATUS_PROCESSING=>'支付中',
            self::STATUS_PAID=>'支付成功',
            self::STATUS_FAILED=>'支付失败',
            self::STATUS_REFUNDED=>'已退款',
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
     * 获取格式化的支付时间
     */
    public function getFormattedPaidAtAttribute()
    {
        return $this->paid_at ? $this->paid_at->format('Y-m-d H:i:s') : null;
    }

    /**
     * 获取状态名称
     */
    public function getStatusNameAttribute(): string
    {
        return self::$status_type[$this->status] ?? '待支付';
    }
}
