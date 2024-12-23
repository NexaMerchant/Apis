<?php

namespace NexaMerchant\Apis\Http\Controllers\Api\V1\Admin\User;

use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use NexaMerchant\Apis\Http\Resources\Api\V1\Admin\Settings\UserResource;
use Webkul\User\Repositories\AdminRepository;

class AuthController extends UserController
{
    use SendsPasswordResetEmails;

    /**
     * Login user.
     *
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request, AdminRepository $adminRepository)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (! EnsureFrontendRequestsAreStateful::fromFrontend($request)) {
            $request->validate(['device_name' => 'required']);

            $admin = $adminRepository->where('email', $request->email)->first();

            if (
                ! $admin
                || ! Hash::check($request->password, $admin->password)
            ) {
                throw ValidationException::withMessages([
                    'email' => trans('Apis::app.admin.account.error.credential-error'),
                ]);
            }
            /**
             * Preventing multiple token creation.
             */
            $admin->tokens()->delete();

            return response([
                'data'    => new UserResource($admin),
                'message' => trans('Apis::app.admin.account.logged-in-success'),
                'token'   => $admin->createToken($request->device_name, ['role:admin'])->plainTextToken,
            ]);
        }

        if (Auth::attempt($request->only(['email', 'password']))) {
            $request->session()->regenerate();

            return response([
                'data'    => new UserResource($this->resolveAdminUser($request)),
                'message' => trans('Apis::app.admin.account.logged-in-success'),
            ]);
            
        }

        // return response([
        //     'message' => trans('Apis::app.admin.account.error.invalid'),
        // ], 401);

        return $this->sendError(trans('Apis::app.admin.account.error.invalid'));
    }

    /**
     * Logout user.
     *
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request)
    {
        $admin = $this->resolveAdminUser($request);

        ! EnsureFrontendRequestsAreStateful::fromFrontend($request)
            ? $admin->tokens()->delete()
            : auth()->guard('admin')->logout();

        return response([
            'message' => trans('Apis::app.admin.account.logged-out-success'),
        ]);
    }

    /**
     * Send forgot password link.
     *
     * @return \Illuminate\Http\Response
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $response = Password::broker('admins')->sendResetLink($request->only('email'));

        return response(
            ['message' => __($response)],
            $response == Password::RESET_LINK_SENT ? 200 : 400
        );
    }
}
