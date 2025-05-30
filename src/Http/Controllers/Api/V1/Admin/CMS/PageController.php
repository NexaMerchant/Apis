<?php

namespace NexaMerchant\Apis\Http\Controllers\Api\V1\Admin\CMS;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Nicelizhi\Manage\Http\Requests\MassDestroyRequest;
use Webkul\CMS\Repositories\CmsRepository as PageRepository; // Change this line
use Webkul\Core\Rules\Slug;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;
use NexaMerchant\Apis\Enum\ApiCacheKey;
use NexaMerchant\Apis\Http\Resources\Api\V1\Admin\CMS\PageResource;

class PageController extends CMSController
{
    /**
     * Repository class name.
     */
    public function repository(): string
    {
        return PageRepository::class;
    }

    /**
     * Resource class name.
     */
    public function resource(): string
    {
        return PageResource::class;
    }

    /**
     * To store a new page in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $locale = core()->getRequestedLocaleCode();
        $request->validate([
            'url_key'      => [
                'required',
                'unique:cms_page_translations,url_key',
                // Rule::unique('cms_page_translations')->where(function ($query) use($locale, $request) {
                //     return $query->where('url_key', $request->input('url_key'))->where('locale', $locale);
                // }),
                new \Webkul\Core\Rules\Slug
            ],
            'page_title'   => 'required',
            'channels'     => 'required',
            'html_content' => 'required',
        ]);

        Event::dispatch('cms.pages.create.before');

        $page = $this->getRepositoryInstance()->create(request()->only([
            'page_title',
            'channels',
            'html_content',
            'meta_title',
            'url_key',
            'meta_keywords',
            'meta_description',
        ]));

        Event::dispatch('cms.pages.create.after', $page);

        // clean cache
        Cache::tags([ApiCacheKey::API_SHOP_CMS])->flush();

        return response([
            'data'    => new PageResource($page),
            'message' => trans('Apis::app.admin.cms.create-success'),
        ]);
    }

    /**
     * To update the previously created page in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, int $id)
    {
        $locale = core()->getRequestedLocaleCode();

        $request->validate([
            $locale.'.url_key'     => ['required', new Slug, function ($attribute, $value, $fail) use ($id, $locale) {
                if (! $this->getRepositoryInstance()->isUrlKeyUnique($id, $value, $locale)) {
                    $fail(trans('Apis::app.admin.cms.error.already-taken'));
                }
            }],
            $locale.'.page_title'   => 'required',
            $locale.'.html_content' => 'required',
            'channels'              => 'required',
        ]);

        Event::dispatch('cms.pages.update.before', $id);

        $page = $this->getRepositoryInstance()->update([
            $locale    => request()->input($locale),
            'channels' => request()->input('channels'),
            'locale'   => $locale,
        ], $id);

        Event::dispatch('cms.pages.update.after', $page);

        // clean cache
        Cache::tags([ApiCacheKey::API_SHOP_CMS])->flush();

        return response([
            'data'    => new PageResource($page),
            'message' => trans('Apis::app.admin.cms.update-success'),
        ]);
    }

    /**
     * To delete the previously create CMS page.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {
        Event::dispatch('cms.pages.delete.before', $id);

        $this->getRepositoryInstance()->delete($id);

        Event::dispatch('cms.pages.delete.after', $id);

        // clean cache
        Cache::tags([ApiCacheKey::API_SHOP_CMS])->flush();

        return response([
            'message' => trans('Apis::app.admin.cms.mass-operations.delete-success'),
        ]);
    }

    /**
     * To mass delete the CMS resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest)
    {
        $indices = $massDestroyRequest->input('indices');

        foreach ($indices as $index) {
            Event::dispatch('cms.pages.delete.before', $index);

            $this->getRepositoryInstance()->delete($index);

            Event::dispatch('cms.pages.delete.after', $index);
        }

        // clean cache
        Cache::tags([ApiCacheKey::API_SHOP_CMS])->flush();

        return response([
            'message' => trans('Apis::app.admin.cms.mass-operations.delete-success'),
        ]);
    }
}
