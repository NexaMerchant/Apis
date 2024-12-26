<?php

namespace NexaMerchant\Apis\Http\Resources\Api\V1\Shop\Catalog;

use NexaMerchant\Apis\Http\Resources\Api\V1\Admin\Catalog\CategoryResource as AdminCategoryResource;

class CategoryResource extends AdminCategoryResource
{

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        /* assign category */
        $category = $this->category ? $this->category : $this;

        $products = $category->products()->limit(10)->get();

        /* generating resource */
        return [
            /* category's information */
            'id'                 => $category->id,
            'name'               => $category->name,
            'slug'               => $category->slug,
            'description'        => $category->description,
            'image'              => $category->image,
            'status'             => $category->status,
            'products_count'     => $category->products_count,
            'parent_id'          => $category->parent_id,
            'products'           => ProductResource::collection($products), // limit 10
            'created_at'         => $category->created_at,
            'updated_at'         => $category->updated_at,
        ];
    }
}
