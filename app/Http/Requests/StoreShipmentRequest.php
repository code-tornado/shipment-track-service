<?php

namespace App\Http\Requests;

use App\Enums\ShipmentStatus;
use App\Enums\ShippingMethod;
use App\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShipmentRequest extends FormRequest
{
    use CargoItemRules, ContainerRules;

    public function rules(): array
    {
        $method = ShippingMethod::tryFrom((string) $this->input('shipping_method'));

        return [
            'reference' => [
                'nullable', 'string', 'max:40',
                Rule::unique('shipments', 'reference')->where('company_id', app(CurrentCompany::class)->id()),
            ],
            'status' => ['nullable', Rule::enum(ShipmentStatus::class)],
            'status_note' => ['nullable', 'string', 'max:1000'],
            'shipping_method' => ['required', Rule::enum(ShippingMethod::class)],
            'shipping_line' => ['nullable', 'string', 'max:100'],
            'origin_port' => ['nullable', 'string', 'max:100'],
            'destination_port' => ['required', 'string', 'max:100'],
            'incoterm' => ['nullable', 'string', 'max:10'],
            'etd' => ['required', 'date'],
            'eta' => ['required', 'date', 'after_or_equal:etd'],
            'ata' => ['nullable', 'date', 'after_or_equal:etd'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'containers' => ['nullable', 'array'],
            ...$this->containerRules('containers.*.', $method),
            'containers.*.cargo_items' => ['nullable', 'array'],
            ...$this->cargoItemRules('containers.*.cargo_items.*.'),
            ...$this->cargoItemDateRules('containers.*.cargo_items.*.'),
        ];
    }

    public function messages(): array
    {
        return [
            'eta.after_or_equal' => 'ETA must be on or after ETD.',
            'ata.after_or_equal' => 'Actual arrival must be on or after ETD.',
        ];
    }
}
