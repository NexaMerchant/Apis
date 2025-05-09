<?php

namespace NexaMerchant\Apis\Http\Resources\Api\V1\Admin\Catalog;

use Illuminate\Http\Resources\Json\JsonResource;
use NexaMerchant\Apis\Http\Resources\Api\V1\Admin\Customer\CustomerResource;

class ProductReviewResource extends JsonResource
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
            'title'      => $this->title,
            'rating'     => $this->rating,
            'comment'    => $this->comment,
            'name'       => $this->name,
            'status'     => $this->status,
            'images'     => $this->images,
            'product'    => new ReviewProductResource($this->product),
            'customer'   => $this->when($this->customer_id, new CustomerResource($this->customer)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
