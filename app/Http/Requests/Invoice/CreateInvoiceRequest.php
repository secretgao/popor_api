<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class CreateInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_id' => 'required|integer|exists:users,id',
            'course_id' => 'required|integer|exists:courses,id',
            'amount' => 'required|numeric|min:0',
            'year_month' => 'required|string|size:6',
            'description' => 'nullable|string|max:500'
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_id.required' => '学生ID不能为空',
            'student_id.integer' => '学生ID必须是整数',
            'student_id.exists' => '学生不存在',
            'course_id.required' => '课程ID不能为空',
            'course_id.integer' => '课程ID必须是整数',
            'course_id.exists' => '课程不存在',
            'amount.required' => '金额不能为空',
            'amount.numeric' => '金额必须是数字',
            'amount.min' => '金额不能小于0',
            'year_month.required' => '年月不能为空',
            'year_month.string' => '年月必须是字符串',
            'year_month.size' => '年月必须是6位数字',
            'description.string' => '描述必须是字符串',
            'description.max' => '描述不能超过500个字符'
        ];
    }
}
