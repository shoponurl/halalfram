<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // guest checkout
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'min:2', 'max:120'],
            'customer_email' => ['required', 'email:rfc', 'max:190'],
            'customer_phone' => ['required', 'string', 'regex:/^[0-9+().\-\s]{10,20}$/'],
            'notes' => ['nullable', 'string', 'max:500'],
            'expected_hold_cents' => ['required', 'integer', 'min:0'],
            'agree_catch_weight' => ['accepted'],
            // Rule 02 / gap 02: the client never sends money or price fields. Reject rather than ignore.
            'amount' => ['prohibited'],
            'amount_cents' => ['prohibited'],
            'price' => ['prohibited'],
            'price_per_lb_cents' => ['prohibited'],
            'hold_cents' => ['prohibited'],
            'total' => ['prohibited'],
            'estimated_cents' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'agree_catch_weight.accepted' => 'Please confirm you understand the final price is based on actual weight.',
            'customer_phone.regex' => 'Please enter a valid phone number.',
        ];
    }
}
