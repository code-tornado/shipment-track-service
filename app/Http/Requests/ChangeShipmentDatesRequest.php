<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ChangeShipmentDatesRequest extends FormRequest
{
    public const DATE_FIELDS = ['etd', 'eta', 'ata', 'customer_delivery_date'];

    public function rules(): array
    {
        return [
            'etd' => ['sometimes', 'date'],
            'eta' => ['sometimes', 'date'],
            'ata' => ['sometimes', 'nullable', 'date'],
            // Applied to every cargo item of the shipment that is not delivered yet.
            'customer_delivery_date' => ['sometimes', 'nullable', 'date'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! array_intersect(array_keys($this->all()), self::DATE_FIELDS)) {
                    $validator->errors()->add('dates', 'Provide at least one date to change (etd, eta, ata or customer_delivery_date).');
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
