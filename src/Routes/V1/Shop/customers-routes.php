<?php

use Illuminate\Support\Facades\Route;
use NexaMerchant\Apis\Http\Controllers\Api\V1\Shop\Customer\AddressController;
use NexaMerchant\Apis\Http\Controllers\Api\V1\Shop\Customer\AuthController;
use NexaMerchant\Apis\Http\Controllers\Api\V1\Shop\Customer\CartController;
use NexaMerchant\Apis\Http\Controllers\Api\V1\Shop\Customer\CheckoutController;
use NexaMerchant\Apis\Http\Controllers\Api\V1\Shop\Customer\InvoiceController;
use NexaMerchant\Apis\Http\Controllers\Api\V1\Shop\Customer\OrderController;
use NexaMerchant\Apis\Http\Controllers\Api\V1\Shop\Customer\ShipmentController;
use NexaMerchant\Apis\Http\Controllers\Api\V1\Shop\Customer\TransactionController;
use NexaMerchant\Apis\Http\Controllers\Api\V1\Shop\Customer\WishlistController;

/**
 * Customer unauthorized routes.
 */
Route::controller(AuthController::class)->prefix('customer')->group(function () {
    Route::post('login', 'login');

    Route::post('register', 'register');

    Route::post('forgot-password', 'forgotPassword');

    // Get the email code
    Route::post('get-code', 'getCode');

    // Login with code
    Route::post('login-code', 'LoginWithCode');

    // Get Guest Token
    Route::post('guest-token', 'getGuestToken');

    
});

// Get Guest Order info
Route::controller(OrderController::class)->prefix('customer/orders')->group(function () {
    Route::get('guest-order-info/{key}', 'guestOrderInfo');
});



/**
 * Customer authorized routes.
 */
Route::group(['middleware' => ['auth:sanctum', 'sanctum.customer']], function () {
    /**
     * Customer auth routes.
     */
    Route::controller(AuthController::class)->prefix('customer')->group(function () {
        Route::get('get', 'get');

        Route::put('profile', 'update');

        Route::post('logout', 'logout');
    });

    /**
     * Customer address routes.
     */
    Route::controller(AddressController::class)->prefix('customer/addresses')->group(function () {
        Route::get('', 'allResources');

        Route::get('{id}', 'getResource');

        Route::post('', 'store');

        Route::put('{id}', 'update');

        Route::delete('{id}', 'destroy');
    });

    /**
     * Customer sale orders routes.
     */
    Route::controller(OrderController::class)->prefix('customer/orders')->group(function () {
        Route::get('', 'allResources');

        Route::get('{id}', 'getResource');

        // guest view order info
        //Route::get('guest/{key}', 'guestOrderInfo');

        Route::post('{id}/cancel', 'cancel');
    });

    /**
     * Customer sale invoices routes.
     */
    Route::controller(InvoiceController::class)->prefix('customer/invoices')->group(function () {
        Route::get('', 'allResources');

        Route::get('{id}', 'getResource');
    });

    /**
     * Customer sale shipment routes.
     */
    Route::controller(ShipmentController::class)->prefix('customer/shipments')->group(function () {
        Route::get('', 'allResources');

        Route::get('{id}', 'getResource');
    });

    /**
     * Customer sale transaction routes.
     */
    Route::controller(TransactionController::class)->prefix('customer/transactions')->group(function () {
        Route::get('', 'allResources');

        Route::get('{id}', 'getResource');
    });

    /**
     * Customer wishlist routes.
     */
    Route::controller(WishlistController::class)->prefix('customer/wishlist')->group(function () {
        Route::get('', 'index');

        Route::post('{id}', 'addOrRemove');

        Route::post('{id}/move-to-cart', 'moveToCart');
    });

    /**
     * Customer cart routes.
     */
    Route::controller(CartController::class)->prefix('customer/cart')->group(function () {
        Route::get('', 'index');

        Route::post('add/{productId}', 'store');

        // batch add products to the cart
        Route::post('batch-add/{productId}', 'batchStore');

        Route::put('update', 'update');


        Route::delete('remove/{cartItemId}', 'removeItem');

        Route::delete('empty', 'empty');

        Route::post('move-to-wishlist/{cartItemId}', 'moveToWishlist');

        Route::post('coupon', 'applyCoupon');

        Route::delete('coupon', 'removeCoupon');

        // paypal
        Route::post('order_addr_after', 'OrderAddrAfter');
        // paypal confirm
        Route::post('order-status', 'OrderStatus');
        // airwallex
        Route::post('order-add-sync', 'OrderAddSync');
    });

    /**
     * Customer checkout routes.
     */
    Route::controller(CheckoutController::class)->prefix('customer/checkout')->group(function () {
        Route::post('save-address', 'saveAddress');

        Route::post('save-shipping', 'saveShipping');

        Route::post('save-payment', 'savePayment');

        Route::post('check-minimum-order', 'checkMinimumOrder');

        Route::post('save-order', 'saveOrder');

        Route::post('quick-checkout', 'quickCheckout');
    });
});
