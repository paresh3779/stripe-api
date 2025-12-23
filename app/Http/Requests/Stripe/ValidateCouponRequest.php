<?php

declare(strict_types=1);

namespace App\Http\Requests\Stripe;

/**
 * Validates coupon validation requests
 */
class ValidateCouponRequest extends BaseStripeRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'coupon_id' => [
                'required',
                'string',
                'uuid',
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
            'coupon_id.required' => 'Coupon ID is required',
            'coupon_id.uuid' => 'Invalid coupon ID format',
        ];
    }
}
