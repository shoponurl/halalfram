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
            'fulfilment_method' => ['required', 'in:pickup,delivery'],
            'delivery_address_line1' => ['required_if:fulfilment_method,delivery', 'nullable', 'string', 'max:190'],
            'delivery_address_line2' => ['nullable', 'string', 'max:190'],
            'delivery_city' => ['required_if:fulfilment_method,delivery', 'nullable', 'string', 'max:80'],
            'delivery_state' => ['required_if:fulfilment_method,delivery', 'nullable', 'string', 'size:2'],
            'delivery_zip' => ['required_if:fulfilment_method,delivery', 'nullable', 'string', 'regex:/^\d{5}$/'],
            'delivery_slot_id' => ['required_if:fulfilment_method,delivery', 'nullable', 'integer', 'exists:delivery_slots,id'],
            // Guideline ch. 6, Sprint 06: cash is pickup-only (checked again server-side in PlaceOrder).
            'payment_method' => ['required', 'in:card,paypal,cash'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'apply_store_credit' => ['nullable', 'boolean'],
            'marketing_sms_opt_in' => ['nullable', 'boolean'],
            'marketing_email_opt_in' => ['nullable', 'boolean'],
            // Rule 02 / gap 02: the client never sends money or price fields. Reject rather than ignore.
            'amount' => ['prohibited'],
            'amount_cents' => ['prohibited'],
            'price' => ['prohibited'],
            'price_per_lb_cents' => ['prohibited'],
            'hold_cents' => ['prohibited'],
            'total' => ['prohibited'],
            'estimated_cents' => ['prohibited'],
            'delivery_fee_cents' => ['prohibited'],
            'tax_cents' => ['prohibited'],
            'discount_cents' => ['prohibited'],
            'store_credit_applied_cents' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'agree_catch_weight.accepted' => 'Please confirm you understand the final price is based on actual weight.',
            'customer_phone.regex' => 'Please enter a valid phone number.',
            'delivery_zip.regex' => 'Please enter a 5-digit zip code.',
        ];
    }
}
