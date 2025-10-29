<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'username' => 'required|string|max:50',
            'password' => 'required|string',
            'role' => 'required|string|in:teacher,student'
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
            'username.required' => '用户名不能为空',
            'username.string' => '用户名必须是字符串',
            'username.max' => '用户名不能超过50个字符',
            'password.required' => '密码不能为空',
            'password.string' => '密码必须是字符串',
            'role.required' => '角色不能为空',
            'role.string' => '角色必须是字符串',
            'role.in' => '角色只能是teacher或student'
        ];
    }
}
