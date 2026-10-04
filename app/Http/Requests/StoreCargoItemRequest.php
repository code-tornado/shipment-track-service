<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCargoItemRequest extends FormRequest
{
    use CargoItemRules;

    public function rules(): array
    {
        return [
            ...$this->cargoItemRules(),
            ...$this->cargoItemDateRules(),
        ];
    }
}
