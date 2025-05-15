<?php

namespace NexaMerchant\Apis\Http\Controllers\Api\V1\Admin\Catalog;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Nicelizhi\Manage\Http\Requests\InventoryRequest;
use Nicelizhi\Manage\Http\Requests\MassDestroyRequest;
use Nicelizhi\Manage\Http\Requests\MassUpdateRequest;
use Nicelizhi\Manage\Http\Requests\ProductForm;
use Webkul\Core\Rules\Slug;
use Webkul\Product\Helpers\ProductType;
use Webkul\Product\Repositories\ProductInventoryRepository;
use Webkul\Product\Repositories\ProductRepository;
use NexaMerchant\Apis\Http\Resources\Api\V1\Admin\Catalog\ProductResource;
use Nicelizhi\Airwallex\Core;
use Illuminate\Support\Facades\Redis;
use NexaMerchant\Apis\Enum\ApiCacheKey;
use Illuminate\Support\Facades\Cache;


class ProductController extends CatalogController
{
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

    public function getResource(int $id)
    {
        $product = $this->getRepositoryInstance()->findOrFail($id);

        return response([
            'data'    => new ProductResource($product),
            'message' => trans('Apis::app.admin.catalog.products.show-success'),
        ]);
    }

    /**
     *
     * Quick Create a new product.
     *
     * @return \Illuminate\Http\Response
     */
    public function quickCreate(Request $request){
        //$request['sku'] = time(); // for test

        DB::beginTransaction();

        try {

            $req = $request->all();
            if(empty($req['id'])) {
                $request->validate([
                    'sku'=> ['required', 'unique:products,sku', new Slug],
                ]);
            }

            $input = [];
            $input['sku'] = $req['sku'];
            $input['type'] = 'configurable';
            $super_attributes = [];
            $super_attributes_label = []; // super attributes label
            $super_attributes_ids = [];

            // create a family attribute by sku
            $attributeFamilyRepository = app('Webkul\Attribute\Repositories\AttributeFamilyRepository');
            $attributeFamily = $attributeFamilyRepository->findOneByField('code', $req['sku']);

            $attribute_group_id = 0;

            $action = 'create';

            if(!$attributeFamily){
                Event::dispatch('catalog.attribute_family.create.before');
                $attributeFamily = $attributeFamilyRepository->create([
                    'code' => $req['sku'],
                    'name' => $req['sku'],
                    'status' => 1,
                    'is_user_defined' => 1
                ]);

                Event::dispatch('catalog.attribute_family.create.after', $attributeFamily);

                // create a default group for the family
                $attributeGroupRepository = app('Webkul\Attribute\Repositories\AttributeGroupRepository');
                Event::dispatch('catalog.attributeGroup.create.before');
                $attributeGroupData = [
                    'name' => 'General',
                    'position' => 1,
                    'attribute_family_id' => $attributeFamily->id
                ];

                $attributeGroup = $attributeFamily->attribute_groups()->create($attributeGroupData);

                Event::dispatch('catalog.attributeGroup.create.before', $attributeGroup);

                // base use attribute group add attribute group mappings
                //$attributeGroupMappingRepository = app('Webkul\Attribute\Repositories\AttributeGroupMappingRepository');
                $attributeGroupItems = $attributeGroupRepository->where('attribute_family_id', $attributeFamily->id)->limit(1)->get();

                //var_dump($attributeGroupItems);exit;

                foreach($attributeGroupItems as $attributeGroupItem) {
                    $attributeGroupMapping = DB::table('attribute_group_mappings')->where("attribute_id", )->where("attribute_group_id", $attributeGroupItem->id)->first();
                    if(!$attributeGroupMapping){
                        $attributeMaxID = 32;
                        $attributeGroupMappingDatas = [];
                        for($i=1;$i<=$attributeMaxID;$i++){

                            // check if the attribute group mapping is have the attribute
                            $attributeGroupMappingData = [
                                'attribute_id' => $i,
                                'attribute_group_id' => $attributeGroupItem->id
                            ];

                            $attributeGroupMappingDatas[] = $attributeGroupMappingData;
                        }
                        DB::table('attribute_group_mappings')->insert($attributeGroupMappingDatas);

                    }
                    $attribute_group_id = $attributeGroupItem->id;
                }
            }else{
                $attributeGroupRepository = app('Webkul\Attribute\Repositories\AttributeGroupRepository');
                $attributeGroup = $attributeGroupRepository->findOneByField('attribute_family_id', $attributeFamily->id);
                $attribute_group_id = $attributeGroup->id;
            }
            $input['attribute_family_id'] = $attributeFamily->id;

            // create super attributes and check if the attribute is valid
            $attributeRepository = app('Webkul\Attribute\Repositories\AttributeRepository');
            $attributeOptionDeleted = [];
            $super_attributes_ids_deleted = [];
            foreach ($req['options'] as $attribute) {
                //var_dump($attribute);exit;

                $code = "attr_".$input['attribute_family_id']."_".md5($attribute['name']);
                $super_attributes_label[$attribute['position']] = $code;

                // create a unique code for the attribute

                $attributeRepos = $attributeRepository->findOneByField('code', $code);
                //var_dump($attributeRepos);exit;
                if(!$attributeRepos){
                    // attribute not found and create a new attribute
                    Event::dispatch('catalog.attribute.create.before');
                    $attributeRepos = $attributeRepository->create([
                        'code' => $code,
                        'admin_name' => $attribute['name'],
                        'type' => 'select',
                        'is_required' => 0,
                        'is_unique' => 0,
                        'validation' => '',
                        'position' => $attribute['position'],
                        'is_visible' => 1,
                        'is_configurable' => 1,
                        'is_filterable' => 1,
                        'use_in_flat' => 0,
                        'is_comparable' => 0,
                        'is_visible_on_front' => 0,
                        'swatch_type' => 'dropdown',
                        'use_in_product_listing' => 1,
                        'use_in_comparison' => 1,
                        'is_user_defined' => 1,
                        'value_per_locale' => 0,
                        'value_per_channel' => 0,
                        'channel_based' => 0,
                        'locale_based' => 0,
                        'default_value' => ''
                    ]);
                    Event::dispatch('catalog.attribute.create.after', $attribute);
                }
                // check if the attribute option is valid
                $attributeOptionRepository = app('Webkul\Attribute\Repositories\AttributeOptionRepository');
                $attributeOptionArray = [];

                // get all value of the attribute
                $attributeOptionItems = $attributeOptionRepository->where('attribute_id', $attributeRepos->id)->pluck('admin_name')->toArray();

                //var_dump($attributeOptionItems->toArray());exit;



                foreach ($attribute['values'] as $option) {

                    if(!$option) continue;

                    //var_dump($option);

                    // check if the attribute option is have in the attributeOptionItems
                    $attributeOption = $attributeOptionRepository->findOneByField(['admin_name'=>$option, 'attribute_id'=>$attributeRepos->id]);
                    if(!$attributeOption){
                        $attributeOption = $attributeOptionRepository->create([
                            'admin_name' => $option,
                            'sort_order' => $attribute['position'],
                            'attribute_id' => $attributeRepos->id
                        ]);
                    }
                    $attributeOptionArray[$attributeOption->id] = $attributeOption->id;
                }
                $super_attributes[$attributeRepos->code] = $attributeOptionArray;
                $super_attributes_ids[$attributeRepos->id] = $attributeRepos->id;

                // check if the attribute option is deleted
                foreach($attributeOptionItems as $attributeOptionItem) {
                    if(!in_array($attributeOptionItem, $attribute['values'])){
                        $deleteAttrOption = $attributeOptionRepository->Where('admin_name', $attributeOptionItem)->where('attribute_id', $attributeRepos->id)->first();
                        $super_attributes_ids_deleted[$attributeRepos->id][] = $deleteAttrOption->id;
                        $attributeOptionDeleted[] = $attributeOptionItem;
                    }
                }



                // delete the attribute option
                if(count($attributeOptionDeleted)){
                    // delete the attribute option id in the attributeOptionArray
                    $deleteAttrOption = $attributeOptionRepository->WhereIn('admin_name', $attributeOptionDeleted)->where('attribute_id', $attributeRepos->id)->delete();
                    //var_dump($deleteAttrOption);



                }

                //var_dump($attributeOptionArray,$super_attributes_ids);exit;


            }

            //var_dump($attributeOptionDeleted, $super_attributes_ids_deleted, $super_attributes_ids);exit;

            $input['super_attributes'] = $super_attributes;
            $input['channel'] = Core()->getCurrentChannel()->code;
            $input['locale'] = Core()->getCurrentLocale()->code;


            //add attribut id to attribute_group_mappings
            $attributeGroupMappingRespos = app();
            foreach($super_attributes_ids as $key=>$super_attributes_id) {
                $attribute_group_mapping = DB::table('attribute_group_mappings')->where("attribute_id", $super_attributes_id)->where("attribute_group_id", $attribute_group_id)->first();
                if(!$attribute_group_mapping){
                    DB::table('attribute_group_mappings')->insert([
                        'attribute_id' => $super_attributes_id,
                        'attribute_group_id' => $attribute_group_id
                    ]);
                }
            }

            // delete the attribute group mapping
            foreach($super_attributes_ids_deleted as $key=>$super_attributes_id) {
                $attribute_group_mapping = DB::table('attribute_group_mappings')->where("attribute_id", $super_attributes_id)->where("attribute_group_id", $attribute_group_id)->delete();
            }

            if($req['id']){

                // update the product super_attributes
                Event::dispatch('catalog.product.update.before', $req['id']);

                $product = $this->getRepositoryInstance()->findOrFail($req['id']);

                // delete the product super attributes info
                DB::table('product_super_attributes')->where('product_id', $req['id'])->delete();

                //$product->update($input);
                $id = $req['id'];

                // add new super attributes
                foreach($super_attributes_ids as $key=>$super_attributes_id) {
                    DB::table('product_super_attributes')->insert([
                        'product_id' => $id,
                        'attribute_id' => $super_attributes_id
                    ]);
                }

                Event::dispatch('catalog.product.update.after', $product);
            // exit;
            $action = 'update';

            }else{
                Event::dispatch('catalog.product.create.before');
                $product = $this->getRepositoryInstance()->create($input);
                Event::dispatch('catalog.product.create.after', $product);
                $id = $product->id;
            }

            $multiselectAttributeCodes = [];
            $productAttributes = $this->getRepositoryInstance()->findOrFail($id);

            //$data = $request->all();

            Event::dispatch('catalog.product.update.before', $id);

            $tableData = [];
            $skus = $request->input('tableData');

            $categories = $request->input('categories');
            // $categories[] = 5;

            $Variants = [];
            $VariantsImages = [];

            $variantCollection = $product->variants()->get()->toArray(); // get the variants of the product
            $variantCollection = array_column($variantCollection, null, 'sku');

            if($action =='create') {
                $product->variants()->delete(); // delete the variants of the product
            }

            // match the variants to the sku id

            // print_r($categories);
            $i = 0;
            foreach($skus as $key=>$sku) {
                $Variant = [];
                $sku['sku'] = !empty($sku['sku']) ? $sku['sku'] : $sku['label'];

                $Variant['name'] = $sku['label'];
                $Variant['price'] = $sku['price'];
                $Variant['weight'] = "1000";
                $Variant['status'] = $req['status'];
                $Variant['inventories'][1] = 1000;
                $Variant['channel'] = Core()->getCurrentChannel()->code;
                $Variant['locale'] = Core()->getCurrentLocale()->code;
                $Variant['visible_individually'] = 1;
                $Variant['guest_checkout'] = 1;
                $Variant['new'] = 1;
                $Variant['attribute_family_id'] = $attributeFamily->id;
                $Variant['manage_stock'] = 0;
                $Variant['visible_individually'] = 1;
                $Variant['product_number'] = 10000;


                $Variant['categories'] = $categories;
                $option1 = isset($super_attributes_label[1]) ? $super_attributes_label[1] : null;
                $option2 = isset($super_attributes_label[2]) ? $super_attributes_label[2] : null;
                $option3 = isset($super_attributes_label[3]) ? $super_attributes_label[3] : null;

                //if($option1) $Variant[$option1] = $sku['option1'];
                if($option1) $Variant[$option1] = $this->findAttributeOptionID($option1, $sku['option1']);
                if($option2) $Variant[$option2] = $this->findAttributeOptionID($option2, $sku['option2']);
                if($option3) $Variant[$option3] = $this->findAttributeOptionID($option3, $sku['option3']);

                $Variant['custom_sku'] = $sku['custom_sku'] ?? '';
                if(empty($sku['id'])) {
                    //$Variant['sku'] = $input['sku'].'-'. $sku['sku'];
                    $Variant['sku'] = $sku['sku'];
                    $Variants["variant_".$i] = $Variant;
                    $i++;
                }else{
                    // use sku to find the variant
                    $Variant['sku'] = $sku['sku'];
                    $Variants[$sku['id']] = $Variant;
                }
            }

            $tableData['channel'] = Core()->getCurrentChannel()->code;
            $tableData['locale'] = Core()->getCurrentLocale()->code;
            $tableData['variants'] = $Variants;
            $tableData['url_key'] = isset($req['url_key']) ? $req['url_key'] : $req['sku'];
            $tableData['name'] = $req['name'];
            $tableData['new'] = 1;
            $tableData['sku'] = $req['sku'];
            $tableData['guest_checkout'] = 1;
            $tableData['status'] = $req['status'];
            $tableData['description'] = $req['description'];
            $tableData['price'] = $req['pricingData']['price'];
            $tableData['compare_at_price'] = $req['pricingData']['originalPrice'];
            $tableData['visible_individually'] = 1;
            $tableData['manage_stock'] = 0;
            $tableData['inventories'][1] = 1000;
            $tableData['product_number'] = 10000;
            $tableData['categories'] = $categories;
            $tableData['meta_title'] = $req['meta_title'];
            $tableData['meta_keywords'] = $req['meta_keywords'];
            $tableData['meta_description'] = $req['meta_description'];


            try {
                $product = $this->getRepositoryInstance()->update($tableData, $id);
            } catch (\Exception $e) {
                DB::rollBack();
                return response([
                    'message' => $e->getMessage(),
                    'Variants' => $Variants,
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ], 400);
            }

            Event::dispatch('catalog.product.update.after', $product);

            $images = $request->input('images');

            // delete the product images
            $product->images()->delete();

            // add images to the product
            $productImages = [];
            foreach($images as $key=>$image) {
                $productImages[] = [
                    'path' => $image['url'],
                    'type' => 'images',
                    'position' => $key
                ];
            }

            $product->images()->createMany($productImages);



            // add images to the variants
            $variants = $product->variants()->get();
            foreach($variants as $key=> $variant) {
                $variantImages = [];
                $image_url = $skus[$key]['images'];
                if($image_url) {
                    $variantImages[] = [
                        'path' => $image_url,
                        'type' => 'images',
                        'position' => 0
                    ];
                    $variant->images()->createMany($variantImages);
                }
            }

            DB::commit();

            // clean admin cache
            Cache::tags([ApiCacheKey::API_ADMIN_PRODUCTS])->flush();
            // clean shop cache
            Cache::tags([ApiCacheKey::API_SHOP_PRODUCTS])->flush();
            Cache::tags(ApiCacheKey::API_SHOP_CATEGORY)->flush();

            return response([
                'data'    => new ProductResource($product),
                'message' => trans('Apis::app.admin.catalog.products.create-success'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 400);
        }
    }

    //
    public function findAttributeOptionID($attribute_id, $attribute_value) {

        //
        $attributeRepository = app('Webkul\Attribute\Repositories\AttributeRepository');

        $attribute = $attributeRepository->findOneByField('code', $attribute_id);
        if(!$attribute) return 0;

        //Log::info("findAttributeOptionID: ".$attribute->id." ".$attribute_value);

        //
        $attributeOptionRepository = app('Webkul\Attribute\Repositories\AttributeOptionRepository');
        $attributeOption = $attributeOptionRepository->findOneByField(['admin_name'=>$attribute_value, 'attribute_id'=>$attribute->id]);
        if($attributeOption){

            // check the attribute_option_translations table
            $attributeOptionTranslationRepository = app('Webkul\Attribute\Repositories\AttributeOptionTranslationRepository');
            $locale = Core()->getCurrentLocale()->code;
            $attributeOptionTranslation = $attributeOptionTranslationRepository->findOneByField(['label'=>$attribute_value, 'locale'=>$locale, 'attribute_option_id'=>$attributeOption->id]);
            //echo $attributeOption->id;
            if(!$attributeOptionTranslation){
                $attributeOptionTranslation = $attributeOptionTranslationRepository->create([
                    'label' => $attribute_value,
                    'locale' => $locale,
                    'attribute_option_id' => $attributeOption->id
                ]);
            }


            return $attributeOption->id;
        }
        return 0;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (
            ProductType::hasVariants($request->input('type'))
            && (! $request->has('super_attributes')
                || ! count($request->get('super_attributes')))
        ) {
            return response([
                'message' => trans('rest-api::app.admin.catalog.products.error.configurable-error'),
            ], 400);
        }

        $request->validate([
            'type'                => 'required',
            'attribute_family_id' => 'required',
            'sku'                 => ['required', 'unique:products,sku', new Slug],
        ]);

        Event::dispatch('catalog.product.create.before');

        $product = $this->getRepositoryInstance()->create($request->all());

        Event::dispatch('catalog.product.create.after', $product);

        return response([
            'data'    => new ProductResource($product),
            'message' => trans('rest-api::app.admin.catalog.products.create-success'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(ProductForm $request, int $id)
    {
        $data = $request->all();

        $multiselectAttributeCodes = [];

        $productAttributes = $this->getRepositoryInstance()->findOrFail($id);

        foreach ($productAttributes->attribute_family->attribute_groups as $attributeGroup) {
            $customAttributes = $productAttributes->getEditableAttributes($attributeGroup);

            if (count($customAttributes)) {
                foreach ($customAttributes as $attribute) {
                    if ($attribute->type == 'multiselect' || $attribute->type == 'checkbox') {
                        array_push($multiselectAttributeCodes, $attribute->code);
                    }
                }
            }
        }

        if (count($multiselectAttributeCodes)) {
            foreach ($multiselectAttributeCodes as $multiselectAttributeCode) {
                if (! isset($data[$multiselectAttributeCode])) {
                    $data[$multiselectAttributeCode] = [];
                }
            }
        }
        Event::dispatch('catalog.product.update.before', $id);

        $product = $this->getRepositoryInstance()->update($data, $id);

        Event::dispatch('catalog.product.update.after', $product);

        return response([
            'data'    => new ProductResource($product),
            'message' => trans('Apis::app.admin.catalog.products.update-success'),
        ]);
    }

    /**
     * Update inventories.
     *
     * @return \Illuminate\Http\Response
     */
    public function updateInventories(InventoryRequest $inventoryRequest, ProductInventoryRepository $productInventoryRepository, int $id)
    {
        $product = $this->getRepositoryInstance()->findOrFail($id);

        Event::dispatch('catalog.product.update.before', $id);

        $productInventoryRepository->saveInventories($inventoryRequest->all(), $product);

        Event::dispatch('catalog.product.update.after', $product);

        return response()->json([
            'data'    => [
                'total' => $productInventoryRepository->where('product_id', $product->id)->sum('qty'),
            ],
            'message' => trans('Apis::app.admin.catalog.products.inventories.update-success'),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {
        $this->getRepositoryInstance()->findOrFail($id);

        Event::dispatch('catalog.product.delete.before', $id);

        $this->getRepositoryInstance()->delete($id);

        // delete the product rules
        $rules = Redis::smembers('product-quantity-rules-'.$id);
        $cartrulerepository = app('Webkul\CartRule\Repositories\CartRuleRepository');

        foreach($rules as $rule) {

            // delete the rule
            $cartrulerepository->delete($rule);
        }

        // delete the product quantity rules
        Redis::del('product-quantity-rules-'.$id);
        Redis::del('product-quantity-price-'.$id);

        // clean admin cache
        Cache::tags([ApiCacheKey::API_ADMIN_PRODUCTS])->flush();
        // clean shop cache
        Cache::tags([ApiCacheKey::API_SHOP_PRODUCTS])->flush();
        Cache::tags(ApiCacheKey::API_SHOP_CATEGORY)->flush();

        Event::dispatch('catalog.product.delete.after', $id);

        return response([
            'message' => trans('Apis::app.admin.catalog.products.delete-success'),
        ]);
    }

    /**
     * Remove the specified resources from database.
     *
     * @return \Illuminate\Http\Response
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest)
    {
        $productIds = $massDestroyRequest->input('indices');

        foreach ($productIds as $productId) {
            Event::dispatch('catalog.product.delete.before', $productId);

            $this->getRepositoryInstance()->delete($productId);

            Event::dispatch('catalog.product.delete.after', $productId);
        }

        // clean admin cache
        Cache::tags([ApiCacheKey::API_ADMIN_PRODUCTS])->flush();
        // clean shop cache
        Cache::tags([ApiCacheKey::API_SHOP_PRODUCTS])->flush();
        Cache::tags(ApiCacheKey::API_SHOP_CATEGORY)->flush();

        return response([
            'message' => trans('Apis::app.admin.catalog.products.mass-operations.delete-success'),
        ]);
    }

    /**
     * Mass update the products.
     *
     * @return \Illuminate\Http\Response
     */
    public function massUpdate(MassUpdateRequest $massUpdateRequest)
    {
        foreach ($massUpdateRequest->indices as $id) {
            $this->getRepositoryInstance()->findOrFail($id);

            Event::dispatch('catalog.product.update.before', $id);

            $product = $this->getRepositoryInstance()->update([
                'channel' => null,
                'locale'  => null,
                'status'  => $massUpdateRequest->value,
            ], $id);

            Event::dispatch('catalog.product.update.after', $product);
        }

        // clean admin cache
        Cache::tags([ApiCacheKey::API_ADMIN_PRODUCTS])->flush();
        // clean shop cache
        Cache::tags([ApiCacheKey::API_SHOP_PRODUCTS])->flush();
        Cache::tags(ApiCacheKey::API_SHOP_CATEGORY)->flush();

        return response([
            'message' => trans('Apis::app.admin.catalog.products.mass-operations.update-success'),
        ]);
    }

    /**
     * Upload product images.
     *
     * @return \Illuminate\Http\Response
     */

    public function upload(Request $request)
    {
        $this->upload($request->all(), 'images');

        return response([], 201);

    }
}
