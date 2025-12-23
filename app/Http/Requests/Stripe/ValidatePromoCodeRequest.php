<?php

declare(strict_types=1);

namespace App\Http\Requests\Stripe;

/**
 * Validates promo code validation requests
 */
class ValidatePromoCodeRequest extends BaseStripeRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'alpha_num',
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
            'code.required' => 'Promo code is required',
            'code.min' => 'Promo code must be at least 3 characters',
            'code.max' => 'Promo code cannot exceed 50 characters',
            'code.alpha_num' => 'Promo code must contain only letters and numbers',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper(trim($this->code)),
            ]);
        }
    }
}
