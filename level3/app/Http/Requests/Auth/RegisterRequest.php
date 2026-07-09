<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Mỗi email không được đăng ký nhiều hơn một tài khoản (YC-5.2).
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            // Mật khẩu tối thiểu 8 ký tự (YC-5.1).
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
