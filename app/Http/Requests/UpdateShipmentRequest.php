<?php

namespace App\Http\Requests;

use App\Enums\ShippingMethod;
use App\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Descriptive fields only. Status goes through POST …/status and dates
 * through PATCH …/dates so that history and audit trail are always written.
 */
class UpdateShipmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reference' => [
                'sometimes', 'string', 'max:40',
                Rule::unique('shipments', 'reference')
                    ->where('company_id', app(CurrentCompany::class)->id())
                    ->ignore($this->route('shipment')),
            ],
            'shipping_method' => ['sometimes', Rule::enum(ShippingMethod::class)],
            'shipping_line' => ['nullable', 'string', 'max:100'],
            'origin_port' => ['nullable', 'string', 'max:100'],
            'destination_port' => ['sometimes', 'string', 'max:100'],
            'incoterm' => ['nullable', 'string', 'max:10'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
