<?php

namespace App\Http\Requests;

use App\Enums\ShippingMethod;
use App\Rules\ContainerNumber;
use Illuminate\Validation\Rule;

trait ContainerRules
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function containerRules(
        string $prefix,
        ?ShippingMethod $method,
        bool $partial = false,
        ?int $shipmentId = null,
        ?int $ignoreId = null,
    ): array {
        $required = $partial ? 'sometimes' : 'required';

        $uniqueInShipment = $shipmentId
            ? [Rule::unique('containers', 'container_no')->where('shipment_id', $shipmentId)->ignore($ignoreId)]
            : ['distinct:ignore_case'];

        return [
            $prefix.'container_no' => [$required, 'string', 'max:20', new ContainerNumber($method), ...$uniqueInShipment],
            $prefix.'seal_no' => ['nullable', 'string', 'max:40'],
            $prefix.'container_type' => ['nullable', 'string', 'max:10'],
            $prefix.'customs_cleared' => ['nullable', 'boolean'],
            $prefix.'storage_facility' => ['nullable', 'string', 'max:100'],
            $prefix.'storage_date' => ['nullable', 'date'],
            $prefix.'pickup_date' => ['nullable', 'date'],
        ];
    }
}
