<?php

namespace NexaMerchant\Apis\Http\Controllers\Api\V1\Shop;

use Illuminate\Http\Request;
use NexaMerchant\Apis\Contracts\ResourceContract;
use NexaMerchant\Apis\Http\Controllers\Api\V1\V1Controller;
use NexaMerchant\Apis\Traits\ProvideResource;
use NexaMerchant\Apis\Traits\ProvideUser;

class ResourceController extends V1Controller implements ResourceContract
{
    use ProvideResource, ProvideUser;

    /**
     * Resource name.
     *
     * @var string
     */
    protected $resourceName = 'Resource(s)';

    /**
     * These are ignored during request.
     *
     * @var array
     */
    protected $requestException = ['page', 'limit', 'pagination', 'sort', 'order', 'token','clean-cache'];

    /**
     * Returns a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function allResources(Request $request)
    {
        $query = $this->getRepositoryInstance()->scopeQuery(function ($query) use ($request) {

            $filterable = $request->except($this->requestException);

            //var_dump($request->all());exit;

            foreach ($filterable as $input => $value) {
                $relations = explode('-', $input);
                $fieldWithOperator = array_pop($relations);
    
                if (strpos($fieldWithOperator, '__') !== false) {
                    [$field, $operator] = explode('__', $fieldWithOperator);
                } else {
                    $field = $fieldWithOperator;
                    $operator = 'eq';
                }
    
                $operatorMap = [
                    'eq' => '=',
                    'neq' => '<>',
                    'gt' => '>',
                    'lt' => '<',
                    'gte' => '>=',
                    'lte' => '<=',
                    'like' => 'like',
                ];
    
                $operator = $operatorMap[$operator] ?? '=';
    
                if (!empty($relations)) {
                    $relation = implode('.', $relations);
    
                    $query = $query->whereHas($relation, function ($q) use ($field, $operator, $value) {
                        if ($operator === 'like') {
                            $value = "%{$value}%";
                        }
                        $q->where($field, $operator, $value);
                    });
                } else {
                    if ($operator === 'like') {
                        $value = "%{$value}%";
                    }
                    $query = $query->where($field, $operator, $value);
                }
            }

            // foreach ($request->except($this->requestException) as $input => $value) {
            //     $query = $query->whereIn($input, array_map('trim', explode(',', $value)));
            // }

            if ($sort = $request->input('sort')) {
                $query = $query->orderBy($sort, $request->input('order') ?? 'desc');
            } else {
                $query = $query->orderBy('id', 'desc');
            }

            return $query;
        });

        if (is_null($request->input('pagination')) || $request->input('pagination')) {
            $results = $query->paginate($request->input('limit') ?? 10);
        } else {
            $results = $query->get();
        }

        return $this->getResourceCollection($results);
    }

    /**
     * Returns an individual resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getResource(Request $request, $id)
    {
        $resourceClassName = $this->resource();

        $resource = $this->isAuthorized()
            ? $this->getRepositoryInstance()->where('customer_id', $this->resolveShopUser($request)->id)->findOrFail($id)
            : $this->getRepositoryInstance()->findOrFail($id);

        return new $resourceClassName($resource);
    }
}
