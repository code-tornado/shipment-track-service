<?php

namespace App\Services;

use App\Enums\NetType;
use App\Models\Customer;
use App\Models\Product;

/**
 * Finds or creates the Suppli reference records (customers, products) a
 * cargo item points at. Names are matched case- and whitespace-insensitively
 * so "GARWARE TECHNICAL FIBRES AS" and "Garware Technical Fibres AS" are one
 * customer.
 */
class ReferenceResolver
{
    /** @var array<string, Customer> */
    private array $customers = [];

    /** @var array<string, Product> */
    private array $products = [];

    public function customer(string $name, ?string $suppliRef = null): Customer
    {
        $key = Customer::normalizeName($name);

        if (! isset($this->customers[$key])) {
            $customer = Customer::query()->where('name_normalized', $key)->first()
                ?? Customer::create(['name' => $name, 'suppli_ref' => $suppliRef]);

            if ($suppliRef && ! $customer->suppli_ref) {
                $customer->update(['suppli_ref' => $suppliRef]);
            }

            $this->customers[$key] = $customer;
        }

        return $this->customers[$key];
    }

    public function product(?string $materialCode, string $description, ?string $netTypeLabel = null): Product
    {
        $materialCode = trim((string) $materialCode) ?: null;
        $description = trim(preg_replace('/\s+/', ' ', $description));
        $key = $materialCode ? 'code:'.$materialCode : 'desc:'.mb_strtolower($description);

        if (! isset($this->products[$key])) {
            $product = null;

            if ($materialCode) {
                $product = Product::query()->where('material_code', $materialCode)->first();
            }
            if (! $product) {
                // No code (or unknown code): fall back to the description, which
                // is how the delivery plan identifies the few rows without a code.
                $product = Product::query()
                    ->whereLike('description', $description)
                    ->when($materialCode, fn ($q) => $q->whereNull('material_code'))
                    ->first();

                if ($product && $materialCode && ! $product->material_code) {
                    $product->update(['material_code' => $materialCode]);
                }
            }

            $product ??= Product::create([
                'material_code' => $materialCode,
                'description' => $description,
                'net_type' => NetType::fromLabel($netTypeLabel, $description),
            ]);

            $this->products[$key] = $product;
        }

        return $this->products[$key];
    }

    public function reset(): void
    {
        $this->customers = [];
        $this->products = [];
    }
}
