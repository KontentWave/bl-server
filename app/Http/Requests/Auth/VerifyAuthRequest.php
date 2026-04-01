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
            'challenge_id' => ['required', 'string', 'uuid'],
            'otp' => ['required', 'string', 'regex:/^\d{6}$/'],
            'public_key' => ['required', 'string'],
            'signature' => ['required', 'string'],
        ];
    }
}
