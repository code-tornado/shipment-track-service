<?php

namespace App\Models;

use App\Tenancy\BelongsToCompany;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'name', 'name_normalized', 'suppli_ref'])]
class Customer extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    public static function normalizeName(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $name)));
    }

    protected static function booted(): void
    {
        static::saving(function (Customer $customer): void {
            $customer->name = trim(preg_replace('/\s+/', ' ', $customer->name));
            $customer->name_normalized = self::normalizeName($customer->name);
        });
    }

    public function cargoItems(): HasMany
    {
        return $this->hasMany(CargoItem::class);
    }
}
