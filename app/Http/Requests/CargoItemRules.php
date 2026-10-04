<?php

namespace App\Http\Requests;

use App\Enums\NetType;
use App\Tenancy\CurrentCompany;
use Illuminate\Validation\Rule;

/**
 * Validation rules for a cargo item payload, reused by the shipment,
 * container and cargo item endpoints (prefixed for nested payloads).
 */
trait CargoItemRules
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function cargoItemRules(string $prefix = '', bool $partial = false, ?int $ignoreId = null): array
    {
        $companyId = app(CurrentCompany::class)->id();
        $required = $partial ? 'sometimes' : 'required';

        return [
            $prefix.'proforma_invoice_no' => [$required, 'string', 'max:40'],
            $prefix.'exporter_ref' => ['nullable', 'string', 'max:40'],
            $prefix.'customer_po' => ['nullable', 'string', 'max:40'],
            $prefix.'tag_no' => ['nullable', 'string', 'max:40'],
            $prefix.'package_no' => [
                'nullable', 'string', 'max:40', 'distinct',
                Rule::unique('cargo_items', 'package_no')
                    ->where('company_id', $companyId)
                    ->ignore($ignoreId),
            ],
            $prefix.'quantity' => ['nullable', 'integer', 'min:1'],
            $prefix.'unit' => ['nullable', 'string', 'max:10'],
            $prefix.'net_weight_kg' => ['nullable', 'numeric', 'min:0'],
            $prefix.'gross_weight_kg' => ['nullable', 'numeric', 'min:0'],
            $prefix.'is_stock' => ['nullable', 'boolean'],
            $prefix.'certificate_sent' => ['nullable', 'boolean'],
            $prefix.'comments' => ['nullable', 'string', 'max:2000'],

            $prefix.'customer_id' => [
                $partial ? 'sometimes' : 'required_without:'.$prefix.'customer_name',
                'integer',
                Rule::exists('customers', 'id')->where('company_id', $companyId),
            ],
            $prefix.'customer_name' => [
                $partial ? 'sometimes' : 'required_without:'.$prefix.'customer_id',
                'string', 'max:150',
            ],
            $prefix.'product_id' => [
                $partial ? 'sometimes' : 'required_without:'.$prefix.'product',
                'integer',
                Rule::exists('products', 'id')->where('company_id', $companyId),
            ],
            $prefix.'product' => [$partial ? 'sometimes' : 'required_without:'.$prefix.'product_id', 'array'],
            $prefix.'product.material_code' => ['nullable', 'string', 'max:40'],
            $prefix.'product.description' => ['required_with:'.$prefix.'product', 'string', 'max:200'],
            $prefix.'product.net_type' => ['nullable', Rule::enum(NetType::class)],
        ];
    }

    /**
     * Dates are only accepted on creation; afterwards they change through the
     * audited date endpoints.
     *
     * @return array<string, list<mixed>>
     */
    protected function cargoItemDateRules(string $prefix = ''): array
    {
        return [
            $prefix.'customer_delivery_date' => ['nullable', 'date'],
            $prefix.'delivered_at' => ['nullable', 'date'],
        ];
    }
}
