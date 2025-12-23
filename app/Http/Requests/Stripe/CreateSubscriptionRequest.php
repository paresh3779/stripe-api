<?php

declare(strict_types=1);

namespace App\Http\Requests\Stripe;

/**
 * Validates subscription creation requests
 */
class CreateSubscriptionRequest extends BaseStripeRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'price_id' => [
                'required',
                'string',
                'uuid',
                'exists:prices,id',
            ],
            'payment_method_id' => [
                'required',
                'string',
                'regex:/^pm_[a-zA-Z0-9]+$/',
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
            'price_id.required' => 'Price ID is required',
            'price_id.uuid' => 'Invalid price ID format',
            'price_id.exists' => 'Price not found',
            'payment_method_id.required' => 'Payment method is required',
            'payment_method_id.regex' => 'Invalid payment method format',
        ];
    }
}
