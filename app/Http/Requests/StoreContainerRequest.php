<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContainerRequest extends FormRequest
{
    use CargoItemRules, ContainerRules;

    public function rules(): array
    {
        $shipment = $this->route('shipment');

        return [
            ...$this->containerRules('', $shipment->shipping_method, false, $shipment->id),
            'cargo_items' => ['nullable', 'array'],
            ...$this->cargoItemRules('cargo_items.*.'),
            ...$this->cargoItemDateRules('cargo_items.*.'),
        ];
    }
}
