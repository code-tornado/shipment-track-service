<?php

namespace App\Http\Controllers\Api;

use App\Enums\NetType;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertProductRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Products are Suppli's material master; the service only mirrors code,
 * description and the net type (NP / DF / …).
 */
class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->withCount('cargoItems')
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($q) => $q
                ->whereLike('description', '%'.$term.'%')
                ->orWhereLike('material_code', '%'.$term.'%')))
            ->orderBy('description')
            ->get();

        return response()->json([
            'data' => $products->map(fn (Product $p) => [
                'id' => $p->id,
                'material_code' => $p->material_code,
                'description' => $p->description,
                'net_type' => $p->net_type->value,
                'net_type_label' => $p->net_type->label(),
                'cargo_items_count' => $p->cargo_items_count,
            ]),
        ]);
    }

    public function upsert(UpsertProductRequest $request): JsonResponse
    {
        $data = $request->validated();

        $product = null;
        if (! empty($data['material_code'])) {
            $product = Product::query()->where('material_code', $data['material_code'])->first();
        }
        $product ??= Product::query()->whereNull('material_code')->whereLike('description', $data['description'])->first();

        $created = $product === null;
        $product ??= new Product;
        $product->fill([
            'material_code' => $data['material_code'] ?? $product->material_code,
            'description' => $data['description'],
            'net_type' => isset($data['net_type'])
                ? NetType::from($data['net_type'])
                : ($product->net_type ?? NetType::fromLabel(null, $data['description'])),
        ])->save();

        return response()->json([
            'data' => [
                'id' => $product->id,
                'material_code' => $product->material_code,
                'description' => $product->description,
                'net_type' => $product->net_type->value,
            ],
        ], $created ? 201 : 200);
    }
}
