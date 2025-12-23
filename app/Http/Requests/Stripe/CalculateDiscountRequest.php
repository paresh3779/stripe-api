<?php

declare(strict_types=1);

namespace App\Http\Requests\Stripe;

/**
 * Validates discount calculation requests
 */
class CalculateDiscountRequest extends BaseStripeRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => [
                'required',
                'integer',
                'min:0',
                'max:99999999', // Max $999,999.99
            ],
            'coupon_id' => [
                'required_without:code',
                'nullable',
                'string',
                'uuid',
            ],
            'code' => [
                'required_without:coupon_id',
                'nullable',
                'string',
                'min:3',
                'max:50',
            ],
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
            'amount.required' => 'Amount is required',
            'amount.integer' => 'Amount must be an integer (in cents)',
            'amount.min' => 'Amount cannot be negative',
            'amount.max' => 'Amount exceeds maximum allowed value',
            'coupon_id.required_without' => 'Either coupon ID or promo code is required',
            'code.required_without' => 'Either coupon ID or promo code is required',
        ];
    }
}
