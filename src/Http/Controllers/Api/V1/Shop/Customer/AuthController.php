<?php

namespace NexaMerchant\Apis\Http\Controllers\Api\V1\Shop\Customer;

use Illuminate\Http\Request;
use Webkul\Core\Rules\PhoneNumber;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Password;
use Webkul\Core\Rules\AlphaNumericSpace;
use Illuminate\Validation\ValidationException;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\Core\Repositories\SubscribersListRepository;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Webkul\Customer\Repositories\CustomerGroupRepository;
use Webkul\Shop\Http\Requests\Customer\RegistrationRequest;
use NexaMerchant\Apis\Http\Resources\Api\V1\Shop\Customer\CustomerResource;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use NexaMerchant\Apis\Mail\V1\Customer\SendCodeNotification;
use Illuminate\Support\Facades\Mail;

class AuthController extends CustomerController
{
    use SendsPasswordResetEmails;

    /**
     * Controller instance.
     *
     * @return void
     */
    public function __construct(
        protected CustomerRepository $customerRepository,
        protected CustomerGroupRepository $customerGroupRepository,
        protected SubscribersListRepository $subscriptionRepository
    ) {
        parent::__construct();
    }

    /**
     * Register the customer.
     *
     * @return \Illuminate\Http\Response
     */
    public function register(RegistrationRequest $registrationRequest)
    {
        Event::dispatch('customer.registration.before');

        $customer = $this->customerRepository->create([
            'first_name'        => $registrationRequest->first_name,
            'last_name'         => $registrationRequest->last_name,
            'email'             => $registrationRequest->email,
            'password'          => bcrypt($registrationRequest->password),
            'is_verified'       => 1,
            'channel_id'        => core()->getCurrentChannel()->id,
            'customer_group_id' => $this->customerGroupRepository->findOneWhere(['code' => 'general'])->id,
        ]);

        Event::dispatch('customer.registration.after', $customer);

        return response([
            'message' => trans('Apis::app.shop.customer.accounts.create-success'),
        ]);
    }

    /**
     * Login the customer.
     *
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (! EnsureFrontendRequestsAreStateful::fromFrontend($request)) {
            $request->validate([
                'device_name' => 'required',
            ]);

            $customer = $this->customerRepository->where('email', $request->email)->first();

            if (! $customer || ! Hash::check($request->password, $customer->password)) {
                throw ValidationException::withMessages([
                    'email' => trans('Apis::app.shop.customer.accounts.error.credential-error'),
                ]);
            }

            /**
             * Preventing multiple token creation.
             */
            $customer->tokens()->delete();

            /**
             * Event passed to prepare cart after login.
             */
            Event::dispatch('customer.after.login', $request->get('email'));

            return response([
                'data'    => new CustomerResource($customer),
                'message' => trans('Apis::app.shop.customer.accounts.logged-in-success'),
                'token'   => $customer->createToken($request->device_name, ['role:customer'])->plainTextToken,
            ]);

        }

        if (Auth::attempt($request->only(['email', 'password']))) {
            $request->session()->regenerate();

            return response([
                'data'    => new CustomerResource($this->resolveShopUser($request)),
                'message' => trans('Apis::app.shop.customer.accounts.logged-in-success'),
            ]);
        }

        return response([
            'message' => trans('Apis::app.shop.customer.accounts.error.invalid'),
        ], 401);
    }

    /**
     * Get details for current logged in customer.
     *
     * @return \Illuminate\Http\Response
     */
    public function get(Request $request)
    {
        $customer = $this->resolveShopUser($request);

        return response([
            'data' => new CustomerResource($customer),
        ]);
    }

    /**
     * Update the customer.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $customer = $this->resolveShopUser($request);

        $isPasswordChanged = false;

        $request->validate([
            'first_name'                => ['required', new AlphaNumericSpace()],
            'last_name'                 => ['required', new AlphaNumericSpace()],
            'gender'                    => 'required|in:Other,Male,Female',
            'date_of_birth'             => 'date|before:today',
            'email'                     => 'email|unique:customers,email,'.$customer->id,
            'new_password'              => 'confirmed|min:6|required_with:current_password',
            'new_password_confirmation' => 'required_with:new_password',
            'current_password'          => 'required_with:new_password',
            'image'                     => 'array',
            'image.*'                   => 'mimes:bmp,jpeg,jpg,png,webp',
            'phone'                     => ['required', new PhoneNumber(), 'unique:customers,phone,'.$customer->id],
            'subscribed_to_news_letter' => 'nullable',
        ]);

        $data = $request->all();

        if (core()->getCurrentChannel()->theme === 'default' 
            && !isset($data['image'])) 
        {
            $data['image']['image_0'] = '';
        }

        $data['subscribed_to_news_letter'] = $request->boolean('subscribed_to_news_letter');

        if (! empty($data['current_password'])) {
            if (Hash::check($data['current_password'], $customer->password)) {
                $isPasswordChanged = true;

                $data['password'] = bcrypt($data['new_password']);
            } else {
                return response(['message' => trans('Apis::app.shop.customer.accounts.error.password-mismatch')]);
            }
        } else {
            unset($data['new_password']);
        }

        Event::dispatch('customer.update.before');

        if ($customer = $this->customerRepository->update($data, $customer->id)) {
                if($isPasswordChanged){
                    Event::dispatch('customer.password.update.after', $customer);
                }   

                if ($request->boolean('subscribed_to_news_letter')) {
                $subscription = $this->subscriptionRepository->firstOrNew(['email' => $data['email']]);

                $subscription->fill([
                    'customer_id'   => $customer->id,
                    'is_subscribed' => 1,
                    'channel_id'    => core()->getCurrentChannel()->id,
                    'token'         => uniqid(),
                ])->save();
            } else {
                $this->subscriptionRepository->where('email', $data['email'])->update(['is_subscribed' => 0]);
            }

            if ($request->hasFile('image')) {
                $this->customerRepository->uploadImages($data, $customer);
            } elseif (isset($data['image'])) {
                if (! empty($data['image'])) {
                    Storage::delete((string)$customer->image);
                }
                
                $customer->image = null;

                $customer->save();
            }

            return response([
                'data'    => new CustomerResource($customer),
                'message' => trans('Apis::app.shop.customer.accounts.update-success'),
            ]);
        }

        Event::dispatch('customer.update.after', $customer);

        return response(['message' => trans('Apis::app.shop.customer.accounts.error.update-failed')]);
    }

    /**
     * Logout the customer.
     *
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request)
    {
        $customer = $this->resolveShopUser($request);

        ! EnsureFrontendRequestsAreStateful::fromFrontend($request)
            ? $customer->tokens()->delete()
            : auth()->guard('customer')->logout();

        Event::dispatch('customer.after.logout', $customer->id);

        return response([
            'message' => trans('Apis::app.shop.customer.accounts.logged-out-success'),
        ]);
    }

    /**
     * Send Reset Password Link.
     *
     * @return \Illuminate\Http\Response
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $response = Password::broker('customers')->sendResetLink($request->only(['email']));

        return response(
            ['message' => __($response)],
            $response == Password::RESET_LINK_SENT ? 200 : 400
        );
    }

    /**
     * Send a code to the email.
     *
     * @return \Illuminate\Http\Response
     */
    public function getCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $customer = $this->customerRepository->findOneByField('email', $request->email);

        if (! $customer) {
            return response(['message' =>'Customer not found'], 404);
        }

        $code = mt_rand(100000, 999999);
        Cache::put(md5($request->email), $code, 300); // 5 * 60 minutes

        //send email to user
        Mail::to($customer->email)->send(new SendCodeNotification($code, $customer));


        return response(['message' => trans('Apis::app.shop.customer.code.sent')]);

    }

    public function LoginWithCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code'  => 'required',
            'device_name'   => 'required',
        ]);

        $customer = $this->customerRepository->findOneByField('email', $request->email);

        if (! $customer) {
            return response(['message' => 'Customer not found'], 404);
        }

        $code = Cache::get(md5($request->email));
        if($code!= $request->code){
            return response(['message' => 'Invalid code'], 404);
        }

        //var_dump(EnsureFrontendRequestsAreStateful::fromFrontend($request));exit;

        $customer = $this->customerRepository->where('email', $request->email)->first();

        //var_dump($customer);exit;

        /**
         * Event passed to prepare cart after login.
         */
        Event::dispatch('customer.after.login', $request->get('email'));

        return response([
            'data'    => new CustomerResource($customer),
            'message' => trans('Apis::app.shop.customer.accounts.logged-in-success'),
            'token'   => $customer->createToken($request->device_name, ['role:customer'])->plainTextToken,
        ]);
    }

    /**
     * Generate a guest token.
     * 
     * @return \Illuminate\Http\Response
     */
    public function getGuestToken(Request $request) {
        // Create a guest customer and return the token.

        $request->validate([
            'device_name' => 'required',
        ]);

        $customer = $this->customerRepository->create([]);

        // add customer to customer group
        $customer->customer_group_id = $this->customerGroupRepository->findOneWhere(['code' => 'guest'])->id;
        $customer->save();


        return response([
            'token' => $customer->createToken($request->device_name, ['role:customer'])->plainTextToken,
        ]);

    }
}
