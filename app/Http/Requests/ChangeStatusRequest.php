<?php

namespace App\Http\Requests;

use App\Enums\ShipmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ShipmentStatus::class)],
            'note' => ['nullable', 'string', 'max:1000'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }
}
