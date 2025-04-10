<?php

namespace NexaMerchant\Apis\Http\Resources\Api\V2\Admin\Catalog;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductVideoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id'   => $this->id,
            'type' => $this->type,
            'url'  => $this->url,
        ];
    }
}
