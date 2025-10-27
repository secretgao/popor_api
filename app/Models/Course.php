<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'fee',
        'teacher_id',
        "year_month",
    ];

    protected $casts = [
        'fee' => 'decimal:2',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s'
    ];

    /**
     * 时间格式
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * 获取课程对应的教师
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'teacher_id');
    }

    /**
     * 获取课程的账单
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * 获取课程的学生（多对多关系）
     */
    public function students()
    {
        return $this->belongsToMany(User::class, 'course_student', 'course_id', 'student_id');
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
}
