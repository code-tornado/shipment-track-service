<?php

namespace App\Models;

use App\Enums\NetType;
use App\Tenancy\BelongsToCompany;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'material_code', 'description', 'net_type'])]
class Product extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'net_type' => NetType::class,
        ];
    }

    public function cargoItems(): HasMany
    {
        return $this->hasMany(CargoItem::class);
    }
}
