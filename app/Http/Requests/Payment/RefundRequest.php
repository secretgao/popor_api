<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class RefundRequest extends FormRequest
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
            'charge_id' => 'required|string',
            'amount' => 'nullable|numeric|min:1'
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
            'charge_id.required' => '支付ID不能为空',
            'charge_id.string' => '支付ID必须是字符串',
            'amount.numeric' => '退款金额必须是数字',
            'amount.min' => '退款金额必须大于0'
        ];
    }
}
