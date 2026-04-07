<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_phone_number' => ['required', 'string', 'max:32'],
            'feature' => ['required', 'string', Rule::in(array_keys(config('reporting.features', [])))],
            'public_key' => ['required', 'string'],
            'signature' => ['required', 'string'],
        ];
    }
}
