<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
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
            'amount' => 'required|numeric|min:0',
            'year_month' => 'required|string|size:6'
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
            'amount.required' => '金额不能为空',
            'amount.numeric' => '金额必须是数字',
            'amount.min' => '金额不能小于0',
            'year_month.required' => '年月不能为空',
            'year_month.string' => '年月必须是字符串',
            'year_month.size' => '年月必须是6位数字'
        ];
    }
}
