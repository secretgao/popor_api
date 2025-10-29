<?php

namespace App\Http\Requests\Course;

use Illuminate\Foundation\Http\FormRequest;

class CreateCourseRequest extends FormRequest
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
            'name' => 'required|string|max:200',
            'year_month' => 'required|string|size:6',
            'fee' => 'required|numeric|min:100'
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
            'name.required' => '课程名称不能为空',
            'name.string' => '课程名称必须是字符串',
            'name.max' => '课程名称不能超过200个字符',
            'year_month.required' => '年月不能为空',
            'year_month.string' => '年月必须是字符串',
            'year_month.size' => '年月必须是6位数字',
            'fee.required' => '费用不能为空',
            'fee.numeric' => '费用必须是数字',
            'fee.min' => '费用不能少于100'
        ];
    }
}
