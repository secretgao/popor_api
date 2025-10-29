<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class ProcessPaymentRequest extends FormRequest
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
            'token' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'currency' => 'required|string|in:THB,USD,EUR,JPY,SGD',
            'description' => 'nullable|string|max:255',
            'invoice_id' => 'required|integer|exists:invoices,id'
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
            'token.required' => '支付令牌不能为空',
            'token.string' => '支付令牌必须是字符串',
            'amount.required' => '支付金额不能为空',
            'amount.numeric' => '支付金额必须是数字',
            'amount.min' => '支付金额必须大于0',
            'currency.required' => '货币类型不能为空',
            'currency.string' => '货币类型必须是字符串',
            'currency.in' => '不支持的货币类型',
            'description.string' => '描述必须是字符串',
            'description.max' => '描述不能超过255个字符',
            'invoice_id.required' => '发票ID不能为空',
            'invoice_id.integer' => '发票ID必须是整数',
            'invoice_id.exists' => '发票不存在'
        ];
    }
}
