<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone_number' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
            'password' => ['required', 'string'],
            'public_key' => ['required', 'string'],
            'signature' => ['required', 'string'],
        ];
    }
}
