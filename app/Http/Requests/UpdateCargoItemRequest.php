<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Dates are excluded here: they change through PATCH …/cargo-items/{id}/dates. */
class UpdateCargoItemRequest extends FormRequest
{
    use CargoItemRules;

    public function rules(): array
    {
        return $this->cargoItemRules('', true, $this->route('cargoItem')->id);
    }
}
