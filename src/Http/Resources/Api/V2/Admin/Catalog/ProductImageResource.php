<?php

namespace NexaMerchant\Apis\Http\Resources\Api\V2\Admin\Catalog;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductImageResource extends JsonResource
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
            'id'                 => $this->id,
            'path'               => $this->path,
            'url'                => $this->url,
            'original_image_url' => $this->url,
            'small_image_url'    => $this->path,
            'medium_image_url'   => $this->path,
            'large_image_url'    => $this->path,
        ];
    }
}
