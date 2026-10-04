<?php

namespace App\Http\Requests;

use App\Webhooks\NotifyWebhooks;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebhookEndpointRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:500'],
            'secret' => ['nullable', 'string', 'min:16', 'max:200'],
            'events' => ['nullable', 'array'],
            'events.*' => ['string', Rule::in(['*', NotifyWebhooks::EVENT_STATUS, NotifyWebhooks::EVENT_SHIPMENT_DATES, NotifyWebhooks::EVENT_ITEM_DATES])],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
