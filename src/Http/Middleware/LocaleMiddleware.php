<?php

namespace NexaMerchant\Apis\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Webkul\Core\Repositories\LocaleRepository;

class LocaleMiddleware
{
    /**
     * Create a middleware instance.
     *
     * @return void
     */
    public function __construct(protected LocaleRepository $localeRepository)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $localeCode = $request->header('x-locale');

        if ($localeCode && $this->localeRepository->findOneByField('code', $localeCode)) {
            app()->setLocale($localeCode);

            return $next($request);
        }

        $code = core()->getDefaultChannel()->default_locale;
        if(empty($code)){
            // code load the default env
            $code = env('APP_LOCALE', 'en');
        }else{
            $code = $code->code;
        }

        app()->setLocale($code);

        return $next($request);
    }
}
