<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PrivacyRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // any visitor may ask for their own data
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:delete,access'],
            'customer_email' => ['required', 'email:rfc', 'max:190'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
