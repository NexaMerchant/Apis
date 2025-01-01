<?php

namespace NexaMerchant\Apis\Http\Controllers\Api\V1\Shop\Catalog;

use Illuminate\Http\Request;
use Webkul\Product\Repositories\ProductRepository;
use NexaMerchant\Apis\Http\Resources\Api\V1\Shop\Catalog\ProductResource;

class ProductController extends CatalogController
{
    /**
     * Is resource authorized.
     */
    public function isAuthorized(): bool
    {
        return false;
    }

    /**
     * Repository class name.
     */
    public function repository(): string
    {
        return ProductRepository::class;
    }

    /**
     * Resource class name.
     */
    public function resource(): string
    {
        return ProductResource::class;
    }

    /**
     * Returns a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function allResources(Request $request)
    {
        // support product name and price sorting
        if ($request->has('sort') && $request->get('sort') === 'name') {
            $request->merge(['sort' => 'name', 'order' => $request->get('order', 'asc')]);
        } elseif ($request->has('sort') && $request->get('sort') === 'price') {
            $request->merge(['sort' => 'price', 'order' => $request->get('order', 'asc')]);
        }

        $results = $this->getRepositoryInstance()->getAll($request->all());

        // $results = $this->getRepositoryInstance()->getAll($request->input('category_id'));

        return $this->getResourceCollection($results);
    }

    /**
     * Returns product's additional information.
     *
     * @return \Illuminate\Http\Response
     */
    public function additionalInformation(Request $request, int $id)
    {
        $resource = $this->getRepositoryInstance()->findOrFail($id);

        $additionalInformation = app(\Webkul\Product\Helpers\View::class)
            ->getAdditionalData($resource);

        return response([
            'data' => $additionalInformation,
        ]);
    }

    /**
     * Returns product's additional information.
     *
     * @return \Illuminate\Http\Response
     */
    public function configurableConfig(Request $request, int $id)
    {
        $resource = $this->getRepositoryInstance()->findOrFail($id);

        $configurableConfig = app(\Webkul\Product\Helpers\ConfigurableOption::class)
            ->getConfigurationConfig($resource);

        return response([
            'data' => $configurableConfig,
        ]);
    }
}
