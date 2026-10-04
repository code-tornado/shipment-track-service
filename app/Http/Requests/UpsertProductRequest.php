<?php

namespace App\Http\Requests;

use App\Enums\NetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'material_code' => ['nullable', 'string', 'max:40'],
            'description' => ['required', 'string', 'max:200'],
            'net_type' => ['nullable', Rule::enum(NetType::class)],
        ];
    }
}
