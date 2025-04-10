<?php

namespace NexaMerchant\Apis\Http\Controllers\Api\V1\Admin\Customers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Nicelizhi\Manage\Http\Requests\MassDestroyRequest;
use Nicelizhi\Manage\Http\Requests\MassUpdateRequest;
use Webkul\Product\Repositories\ProductReviewRepository;
use NexaMerchant\Apis\Http\Resources\Api\V1\Admin\Catalog\ProductReviewResource;
use NexaMerchant\Apis\Imports\ProductReviewImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Cache;
use NexaMerchant\Apis\Enum\ApiCacheKey;

class ReviewController extends BaseController
{
    /**
     * Repository class name.
     */
    public function repository(): string
    {
        return ProductReviewRepository::class;
    }

    /**
     * Resource class name.
     */
    public function resource(): string
    {
        return ProductReviewResource::class;
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(int $id)
    {
        $this->validate(request(), [
            'status' => 'in:approved,disapproved,pending',
            "rating" => "numeric|min:1|max:5",
            "sort" => "numeric|min:1|max:100",
        ]);
        
        Event::dispatch('customer.review.update.before', $id);

        $review = $this->getRepositoryInstance()->update(request()->only(['status','sort','rating']), $id);

        Event::dispatch('customer.review.update.after', $review);

        Cache::tags(ApiCacheKey::API_SHOP_PRODUCTS_COMMENTS)->flush();

        return response([
            'data'    => new ProductReviewResource($review),
            'message' => trans('Apis::app.admin.customers.reviews.update-success'),
        ]);
    }

    /**
     * Delete the review.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {
        Event::dispatch('customer.review.delete.before', $id);

        $this->getRepositoryInstance()->delete($id);

        Event::dispatch('customer.review.delete.after', $id);

        Cache::tags(ApiCacheKey::API_SHOP_PRODUCTS_COMMENTS)->flush();

        return response([
            'message' => trans('Apis::app.admin.customers.reviews.delete-success'),
        ]);
    }

    /**
     * Mass approve the reviews.
     *
     * @return \Illuminate\Http\Response
     */
    public function massUpdate(MassUpdateRequest $massUpdateRequest)
    {
        $indices = $massUpdateRequest->input('indices');

        foreach ($indices as $id) {
            Event::dispatch('customer.review.update.before', $id);

            $review = $this->getRepositoryInstance()->update([
                'status' => $massUpdateRequest->input('value'),
            ], $id);

            Event::dispatch('customer.review.update.after', $review);
        }

        Cache::tags(ApiCacheKey::API_SHOP_PRODUCTS_COMMENTS)->flush();

        return response([
            'message' => trans('Apis::app.admin.customers.reviews.mass-operations.update-success'),
        ]);
    }

    /**
     * Mass delete the reviews on the products.
     *
     * @return \Illuminate\Http\Response
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest)
    {
        $indices = $massDestroyRequest->input('indices');

        foreach ($indices as $index) {
            Event::dispatch('customer.review.delete.before', $index);

            $this->getRepositoryInstance()->delete($index);

            Event::dispatch('customer.review.delete.after', $index);
        }

        Cache::tags(ApiCacheKey::API_SHOP_PRODUCTS_COMMENTS)->flush();

        return response([
            'message' => trans('Apis::app.admin.customers.reviews.mass-operations.delete-success'),
        ]);
    }

    /**
     * 
     * product id import a xls file
     * 
     * upload a xls file to import its products review
     */
    public function import(Request $request, $product_id) {

        $this->validate(request(), [
            'file' => 'required|mimes:xls,xlsx',
        ]);

        try {
            Excel::import(new ProductReviewImport, $request->file('file'));

            //clear cache by product id
            Cache::tags(ApiCacheKey::API_SHOP_PRODUCTS_COMMENTS)->flush();

            return response()->json([
                'message' => 'Product reviews imported successfully.',
            ]);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();

            return response()->json([
                'message'  => 'Import failed due to validation errors.',
                'errors'   => $failures,
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred during import.',
                'error'   => $e->getMessage(),
            ], 500);
        }

    }
}
