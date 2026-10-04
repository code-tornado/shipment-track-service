<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContainerRequest extends FormRequest
{
    use ContainerRules;

    public function rules(): array
    {
        $container = $this->route('container');

        return $this->containerRules('', $container->shipment->shipping_method, true, $container->shipment_id, $container->id);
    }
}
