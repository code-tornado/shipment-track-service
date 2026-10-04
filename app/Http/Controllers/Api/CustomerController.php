<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customers are Suppli's; this is the read-only mirror the service keeps.
 * PUT lets Suppli (or an admin) push customers so cargo can reference them.
 */
class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customers = Customer::query()
            ->withCount('cargoItems')
            ->when($request->query('q'), fn ($q, $term) => $q->whereLike('name', '%'.$term.'%'))
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $customers->map(fn (Customer $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'suppli_ref' => $c->suppli_ref,
                'cargo_items_count' => $c->cargo_items_count,
            ]),
        ]);
    }

    public function upsert(UpsertCustomerRequest $request): JsonResponse
    {
        $data = $request->validated();

        $customer = null;
        if (! empty($data['suppli_ref'])) {
            $customer = Customer::query()->where('suppli_ref', $data['suppli_ref'])->first();
        }
        $customer ??= Customer::query()->where('name_normalized', Customer::normalizeName($data['name']))->first();

        $created = $customer === null;
        $customer ??= new Customer;
        $customer->fill($data)->save();

        return response()->json([
            'data' => ['id' => $customer->id, 'name' => $customer->name, 'suppli_ref' => $customer->suppli_ref],
        ], $created ? 201 : 200);
    }
}
