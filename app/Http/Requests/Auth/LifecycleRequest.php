<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LifecycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_id' => ['required', 'string', 'uuid', 'max:36'],
            'public_key' => ['required', 'string', 'max:8192'],
            'signature' => ['required', 'string', 'max:4096'],
            'operation' => ['prohibited'],
        ] + ($this->is('api/auth/renew') ? [
            'enrollment_id' => ['required', 'string', 'uuid', 'max:36'],
            'next_renewal_at' => ['present', 'nullable', 'string', 'max:64'],
        ] : []);
    }
}
