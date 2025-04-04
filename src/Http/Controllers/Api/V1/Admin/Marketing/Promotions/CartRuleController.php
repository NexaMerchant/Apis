<?php

namespace NexaMerchant\Apis\Http\Controllers\Api\V1\Admin\Marketing\Promotions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Webkul\CartRule\Repositories\CartRuleRepository;
use Webkul\CartRule\Repositories\CartRuleProductRepository;
use NexaMerchant\Apis\Http\Controllers\Api\V1\Admin\Marketing\MarketingController;
use NexaMerchant\Apis\Http\Resources\Api\V1\Admin\Marketing\Promotions\CartRuleResource;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CartRuleController extends MarketingController
{
    private $checkout_v2_cache_key = "checkout_v2_cache_";
    /**
     * Repository class name.
     */
    public function repository(): string
    {
        return CartRuleRepository::class;
    }

    /**
     * Resource class name.
     */
    public function resource(): string
    {
        return CartRuleResource::class;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'                => 'required',
            'channels'            => 'required|array|min:1',
            'customer_groups'     => 'required|array|min:1',
            'coupon_type'         => 'required',
            'use_auto_generation' => 'required_if:coupon_type,==,1',
            'coupon_code'         => 'required_if:use_auto_generation,==,0|unique:cart_rule_coupons,code',
            'starts_from'         => 'nullable|date',
            'ends_till'           => 'nullable|date|after_or_equal:starts_from',
            'action_type'         => 'required',
            'discount_amount'     => 'required|numeric',
        ]);

        Event::dispatch('promotions.cart_rule.create.before');

        $cartRule = $this->getRepositoryInstance()->create($request->all());

        Event::dispatch('promotions.cart_rule.create.after', $cartRule);

        return response([
            'data'    => new CartRuleResource($cartRule),
            'message' => trans('Apis::app.admin.marketing.promotions.cart-rules.create-success'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, int $id)
    {
        $request->validate([
            'name'                => 'required',
            'channels'            => 'required|array|min:1',
            'customer_groups'     => 'required|array|min:1',
            'coupon_type'         => 'required',
            'use_auto_generation' => 'required_if:coupon_type,==,1',
            'starts_from'         => 'nullable|date',
            'ends_till'           => 'nullable|date|after_or_equal:starts_from',
            'action_type'         => 'required',
            'discount_amount'     => 'required|numeric',
        ]);

        $cartRule = $this->getRepositoryInstance()->findOrFail($id);

        if ($cartRule->coupon_type) {
            if ($cartRule->cart_rule_coupon) {
                $request->validate([
                    'coupon_code' => 'required_if:use_auto_generation,==,0|unique:cart_rule_coupons,code,'.$cartRule->cart_rule_coupon->id,
                ]);
            } else {
                $request->validate([
                    'coupon_code' => 'required_if:use_auto_generation,==,0|unique:cart_rule_coupons,code',
                ]);
            }
        }

        Event::dispatch('promotions.cart_rule.update.before', $id);

        $cartRule = $this->getRepositoryInstance()->update($request->all(), $id);

        Event::dispatch('promotions.cart_rule.update.after', $cartRule);

        return response([
            'data'    => new CartRuleResource($cartRule),
            'message' => trans('Apis::app.admin.marketing.promotions.cart-rules.update-success'),
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

        Event::dispatch('promotions.cart_rule.delete.before', $id);

        $this->getRepositoryInstance()->delete($id);

        Event::dispatch('promotions.cart_rule.delete.after', $id);

        return response([
            'message' => trans('Apis::app.admin.marketing.promotions.cart-rules.delete-success'),
        ]);
    }

    /**
     *  getConditionAttributes
     * 
     */
    public function getConditionAttributes() {

        $cartRule = app(\Webkul\CartRule\Repositories\CartRuleRepository::class);
       // $categories = app(\Webkul\Category\Repositories\CategoryRepository::class)->getCategoryTree();
       // $categoryRepository = app(\Webkul\Category\Repositories\CategoryRepository::class);
        $attributeRepository = app(\Webkul\Attribute\Repositories\AttributeRepository::class);
        
        $attributes = [
            [
                'key'      => 'cart',
                'label'    => trans('admin::app.marketing.promotions.cart-rules.create.cart-attribute'),
                'children' => [
                    [
                        'key'   => 'cart|base_sub_total',
                        'type'  => 'price',
                        'label' => trans('admin::app.marketing.promotions.cart-rules.create.subtotal'),
                    ], [
                        'key'   => 'cart|items_qty',
                        'type'  => 'integer',
                        'label' => trans('admin::app.marketing.promotions.cart-rules.create.total-items-qty'),
                    ], [
                        'key'     => 'cart|payment_method',
                        'type'    => 'select',
                        'options' => $cartRule->getPaymentMethods(),
                        'label'   => trans('admin::app.marketing.promotions.cart-rules.create.payment-method'),
                    ], [
                        'key'     => 'cart|shipping_method',
                        'type'    => 'select',
                        'options' => $cartRule->getShippingMethods(),
                        'label'   => trans('admin::app.marketing.promotions.cart-rules.create.shipping-method'),
                    ], [
                        'key'   => 'cart|postcode',
                        'type'  => 'text',
                        'label' => trans('admin::app.marketing.promotions.cart-rules.create.shipping-postcode'),
                    ], [
                        'key'     => 'cart|state',
                        'type'    => 'select',
                        'options' => $cartRule->groupedStatesByCountries(),
                        'label'   => trans('admin::app.marketing.promotions.cart-rules.create.shipping-state'),
                    ], [
                        'key'     => 'cart|country',
                        'type'    => 'select',
                        'options' => $cartRule->getCountries(),
                        'label'   => trans('admin::app.marketing.promotions.cart-rules.create.shipping-country'),
                    ],
                ],
            ], [
                'key'      => 'cart_item',
                'label'    => trans('admin::app.marketing.promotions.cart-rules.create.cart-item-attribute'),
                'children' => [
                    [
                        'key'   => 'cart_item|base_price',
                        'type'  => 'price',
                        'label' => trans('admin::app.marketing.promotions.cart-rules.create.price-in-cart'),
                    ], [
                        'key'   => 'cart_item|quantity',
                        'type'  => 'integer',
                        'label' => trans('admin::app.marketing.promotions.cart-rules.create.qty-in-cart'),
                    ], [
                        'key'   => 'cart_item|base_total_weight',
                        'type'  => 'decimal',
                        'label' => trans('admin::app.marketing.promotions.cart-rules.create.total-weight'),
                    ], [
                        'key'   => 'cart_item|base_total',
                        'type'  => 'price',
                        'label' => trans('admin::app.marketing.promotions.cart-rules.create.subtotal'),
                    ], [
                        'key'   => 'cart_item|additional',
                        'type'  => 'text',
                        'label' => trans('admin::app.marketing.promotions.cart-rules.create.additional'),
                    ],
                ],
            ], [
                'key'      => 'product',
                'label'    => trans('admin::app.marketing.promotions.cart-rules.create.product-attribute'),
                'children' => [
                    [
                        'key'     => 'product|category_ids',
                        'type'    => 'multiselect',
                        'label'   => trans('admin::app.marketing.promotions.cart-rules.create.categories'),
                        'options' => [],
                    ], [
                        'key'     => 'product|children::category_ids',
                        'type'    => 'multiselect',
                        'label'   => trans('admin::app.marketing.promotions.cart-rules.create.children-categories'),
                        'options' => [],
                    ], [
                        'key'     => 'product|parent::category_ids',
                        'type'    => 'multiselect',
                        'label'   => trans('admin::app.marketing.promotions.cart-rules.create.parent-categories'),
                        'options' => [],
                    ], [
                        'key'     => 'product|attribute_family_id',
                        'type'    => 'select',
                        'label'   => trans('admin::app.marketing.promotions.cart-rules.create.attribute-family'),
                        'options' => $cartRule->getAttributeFamilies(),
                    ],
                ],
            ],
        ];

        $tempAttributes = $attributeRepository->with([
            'translations',
            'options',
            'options.translations'
        ])->findWhereNotIn('type', [
            'textarea',
            'image',
            'file'
        ]);

        //return $tempAttributes;

        foreach ($tempAttributes as $attribute) {
            $attributeType = $attribute->type;

            if ($attribute->code == 'tax_category_id') {
                $options = $cartRule->getTaxCategories();
            } else {
                $options = $attribute->options;
            }

            if ($attribute->validation == 'decimal') {
                $attributeType = 'decimal';
            } elseif ($attribute->validation == 'numeric') {
                $attributeType = 'integer';
            }

            $attributes[2]['children'][] = [
                'key'     => 'product|' . $attribute->code,
                'type'    => $attribute->type,
                'label'   => $attribute->name,
                'options' => $options,
            ];

            $attributes[2]['children'][] = [
                'key'     => 'product|children::' . $attribute->code,
                'type'    => $attribute->type,
                'label'   => trans('admin::app.marketing.promotions.cart-rules.create.attribute-name-children-only', ['attribute_name' => $attribute->name]),
                'options' => $options,
            ];

            $attributes[2]['children'][] = [
                'key'     => 'product|parent::' . $attribute->code,
                'type'    => $attribute->type,
                'label'   => trans('admin::app.marketing.promotions.cart-rules.create.attribute-name-parent-only', ['attribute_name' => $attribute->name]),
                'options' => $options,
            ];
        }

        //return $attributes;



        return response()->json([
           'data' => $attributes
        ]);
    }

    /**
     * 
     * Create a new cart rule for the product quantity
     * 
     * @param int $product_id
     * @param \Illuminate\Http\Request $request
     * 
     * @return \Illuminate\Http\Response
     */
    public function createProductQuantityRule($product_id, Request $request){
        $product = app(\Webkul\Product\Repositories\ProductRepository::class)->findOrFail($product_id);
        if(!$product){
            return response()->json([
                'message' => trans('admin::app.marketing.promotions.cart-rules.product-not-found')
            ], 404);
        }

        // create a new cart rule for the product quantity
        $request->validate([
            'product_id'                => 'required|integer',
            'rules'                     => 'required|array|min:1|max:4',
        ]);

        $cartRule = app(\Webkul\CartRule\Repositories\CartRuleRepository::class);
        $cartRuleProduct = app(\Webkul\CartRule\Repositories\CartRuleProductRepository::class);

        $rules = $request->rules;

        $product_price = $product->price;

        $product_price = floatval($product_price);

        //var_dump($product_price);exit;

        if($product_price <= 0){
            return false;
        }

        foreach($rules as $rule){

            $rulesAttributes = $rule['attributes'];

            $qty = 0;
            $status = 0;    

            $cartRuleData = [
                'name'                => $product->name . $rule['action_type']. $rule['price'],
                'starts_from'         => null,
                'ends_till'           => null,
                'action_type'         => $rule['action_type'],
                'discount_amount'     => 0,
                'end_other_rules'     => 0,
                'sort_order'          => 100,
                'status'              => $status, // 0 means inactive
                'coupon_type'         => 0,
                'use_auto_generation' => 0,
                'discount_step'       => 0,
                'channels'            => [1],
                'customer_groups'     => [1],
                'discount_quantity'   => 0,
            ];

            foreach($rulesAttributes as $key=>$attribute){

                if($attribute['attribute'] == 'product|attribute_family_id'){
                    $attribute['value'] = $product->attribute_family_id;
                }

                $cartRuleData['conditions'][] = [
                    'attribute' => $attribute['attribute'],
                    'operator' => $attribute['operator'],
                    "attribute_type" => "integer",
                    'value' => $attribute['value'],
                ];
                if($attribute['attribute'] == 'cart|items_qty'){
                    $qty = $attribute['value'];
                }
            }

            if($qty > 1) {
                $status = 1;
                $discount_amount = ($product_price * $qty - $rule['price']) / $qty;
            } else{
                $discount_amount = 0;
                $status = 0;
            }

            //if($qty == 1) {} continue;

            $id = $rule['id'];
            $cartRuleData['discount_amount'] = $discount_amount;
            $cartRuleData['status'] = $status;
            $cartRuleData['discount_quantity'] = $qty;
                        
            if($id > 0) {

                // check the id in the product-quantity-price set
                $price = Redis::zscore('product-quantity-price-'.$product_id, $id);
                if($price == null){
                    return response()->json(['message' => 'ERROR Cart RULE UPDATE'], 400);
                }
                // update the rule
                //$cartRule = $this->getRepositoryInstance()->findOrFail($id);

                // rule attribute_family_id is not equal to product attribute_family_id
                $dbRule = $this->getRepositoryInstance()->find($id);
                $dbRuleAttributes = $dbRule->conditions;

                foreach($dbRuleAttributes as $dbRuleAttribute){
                    if($dbRuleAttribute['attribute'] == 'product|attribute_family_id'){
                        if($dbRuleAttribute['value'] != $product->attribute_family_id){

                            // send the message to the user by feishu
                            \Nicelizhi\Shopify\Helpers\Utils::sendFeishu(config('onebuy.brand').': The cart rule attribute_family_id is not equal to the product attribute_family_id, product_id: '.$product->id.' cart_rule_attribute_family_id_not_equal_to_product_attribute_family_id');

                            $res = [];
                            $res['db'] = $dbRuleAttribute['value'];
                            $res['product'] = $product->attribute_family_id;
                            $res['message'] = "ERROR Cart RULE UPDATE";
                            return response()->json($res, 400);
                        }
                    }
                }

                Event::dispatch('promotions.cart_rule.update.before', $id);

                $cartRule = $this->getRepositoryInstance()->update($cartRuleData, $id);

                Event::dispatch('promotions.cart_rule.update.after', $cartRule);

            }else{

                Event::dispatch('promotions.cart_rule.create.before');

                $cartRule = $this->getRepositoryInstance()->create($cartRuleData);
    
                Event::dispatch('promotions.cart_rule.create.after', $cartRule);
            }
               
            Redis::sadd('product-quantity-rules-'.$product_id, $cartRule->id);

            Redis::zadd('product-quantity-price-'.$product_id, $rule['price'], $cartRule->id);

            // add the product to the cart_rule_product table if not exists
            $cartRuleProductInfo = $cartRuleProduct->findOneWhere([
                'cart_rule_id' => $cartRule->id,
                'product_id' => $product_id
            ]);
            if(!$cartRuleProductInfo) {
                $cartRuleProduct->create([
                    'cart_rule_id' => $cartRule->id,
                    'product_id' => $product_id
                ]);
            }
        }

        // clear the cache in redis
        // product slug
        $slug = $product->url_key;
        $currency = core()->getCurrentCurrency()->code;
        //echo $this->checkout_v2_cache_key.$slug.$currency;
        Cache::forget($this->checkout_v2_cache_key.$slug.$currency);
        Cache::forget("product_ext_".$product->id."_1_".$currency);
        Cache::forget("product_ext_".$product->id."_2_".$currency);
        Cache::forget("product_ext_".$product->id."_3_".$currency);
        Cache::forget("product_ext_".$product->id."_4_".$currency);

        

        return response([
            'data' => $rules,
            'message' => trans('Apis::app.admin.marketing.promotions.cart-rules.create-success'),
        ]);

    }

    /**
     * 
     * Get all Rules for the Product Quantity
     * 
     * @param int $product_id
     * 
     * @return \Illuminate\Http\Response
     * 
     */
    public function getProductQuantityRules($product_id, Request $request){

        

        $product = app(\Webkul\Product\Repositories\ProductRepository::class)->findOrFail($product_id);
        if(!$product){
            return response()->json([
                'message' => trans('admin::app.marketing.promotions.cart-rules.product-not-found')
            ], 404);
        }

        
        // get all the rules for the product quantity

        $rules = Redis::smembers('product-quantity-rules-'.$product_id);

        $prices = Redis::zRange('product-quantity-price-'.$product_id, 0, -1, 'WITHSCORES');

        //var_dump($prices);exit;

        $rules = $this->getRepositoryInstance()->findWhereIn('id', $rules);

        $rules = $rules->map(function($rule, $key) use($prices){
            if(isset($prices[$rule->id])) $rule['discount_amount'] = round($prices[$rule->id], 2);
                
            return $rule;
        });


        return response()->json([
            'data' => CartRuleResource::collection($rules)
        ]);

    }

}
