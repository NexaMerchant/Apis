<?php

namespace NexaMerchant\Apis\Http\Resources\Api\V2\Shop\Customer;

use Illuminate\Http\Resources\Json\JsonResource;
use NexaMerchant\Apis\Http\Resources\Api\V2\Shop\Catalog\ProductResource;

class CustomerWishlistResource extends JsonResource
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
            'id'         => $this->id,
            'product'    => new ProductResource($this->product),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
