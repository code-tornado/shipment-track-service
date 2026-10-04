<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ChangeCargoItemDatesRequest extends FormRequest
{
    public const DATE_FIELDS = ['customer_delivery_date', 'delivered_at'];

    public function rules(): array
    {
        return [
            'customer_delivery_date' => ['sometimes', 'nullable', 'date'],
            'delivered_at' => ['sometimes', 'nullable', 'date'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! array_intersect(array_keys($this->all()), self::DATE_FIELDS)) {
                    $validator->errors()->add('dates', 'Provide at least one date to change (customer_delivery_date or delivered_at).');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A reason is required for every date change.',
        ];
    }
}
