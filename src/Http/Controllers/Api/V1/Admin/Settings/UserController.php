<?php

namespace NexaMerchant\Apis\Http\Controllers\Api\V1\Admin\Settings;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Nicelizhi\Manage\Http\Requests\UserForm;
use NexaMerchant\Apis\Http\Resources\Api\V1\Admin\Settings\UserResource;
use Webkul\User\Repositories\AdminRepository;

class UserController extends SettingController
{
    /**
     * Repository class name.
     */
    public function repository(): string
    {
        return AdminRepository::class;
    }

    /**
     * Resource class name.
     */
    public function resource(): string
    {
        return UserResource::class;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(UserForm $request)
    {
        $data = $request->only([
            'name',
            'email',
            'password',
            'password_confirmation',
            'role_id',
            'status',
        ]);

        if ($data['password'] ?? null) {
            $data['password'] = bcrypt($data['password']);

            $data['api_token'] = Str::random(80);
        }

        Event::dispatch('user.admin.create.before');

        $admin = $this->getRepositoryInstance()->create($data);

        Event::dispatch('user.admin.create.after', $admin);

        return response([
            'user'    => new UserResource($admin),
            'message' => trans('Apis::app.admin.settings.users.create-success'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(UserForm $request, int $id)
    {
        $data = $this->prepareUserData($request, $id);

        if ($data instanceof \Illuminate\Http\Response) {
            return $data;
        }

        Event::dispatch('user.admin.update.before', $id);

        $admin = $this->getRepositoryInstance()->update($data, $id);

        if (! empty($data['password'])) {
            Event::dispatch('admin.password.update.after', $admin);
        }

        Event::dispatch('user.admin.update.after', $admin);

        return response([
            'user'    => new UserResource($admin),
            'message' => trans('Apis::app.admin.settings.users.update-success'),
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

        if ($this->getRepositoryInstance()->count() == 1) {
            return response([
                'message' => trans('Apis::app.admin.settings.users.error.last-item-delete'),
            ], 400);
        }

        Event::dispatch('user.admin.delete.before', $id);

        $this->getRepositoryInstance()->delete($id);

        Event::dispatch('user.admin.delete.after', $id);

        return response([
            'message' => trans('Apis::app.admin.settings.users.delete-success'),
        ]);
    }

    /**
     * Prepare user data.
     *
     * @return array|\Illuminate\Http\RedirectResponse
     */
    private function prepareUserData(UserForm $request, int $id)
    {
        $data = $request->validated();

        $user = $this->getRepositoryInstance()->findOrFail($id);

        /**
         * Password check.
         */
        if (! $data['password']) {
            unset($data['password']);
        } else {
            $data['password'] = bcrypt($data['password']);
        }

        $data['status'] = $data['status'] ?? 0;

        if (
            (int) $data['status'] === 0
            && (int) $user->status === 1
            && $this->getRepositoryInstance()->countAdminsWithAllAccessAndActiveStatus() === 1
        ) {
            return $this->cannotChangeRedirectResponse('status');
        }

        /**
         * Is user with `permission_type` all role changed.
         */
        $isRoleChanged = $user->role->permission_type === 'all'
            && isset($data['role_id'])
            && (int) $data['role_id'] !== $user->role_id;

        if ($isRoleChanged && $this->getRepositoryInstance()->countAdminsWithAllAccess() === 1) {
            return $this->cannotChangeRedirectResponse('role');
        }

        return $data;
    }

    /**
     * Cannot change redirect response.
     *
     * @return \Illuminate\Http\Response
     */
    private function cannotChangeRedirectResponse(string $columnName)
    {
        return response([
            'message' => trans('Apis::app.admin.settings.users.error.cannot-change-column', ['name' => $columnName]),
        ]);
    }
}
