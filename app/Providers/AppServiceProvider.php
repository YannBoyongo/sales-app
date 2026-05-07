<?php

namespace App\Providers;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        App::setLocale(config('app.locale', 'fr'));
        Carbon::setLocale(config('app.locale', 'fr'));
        Schema::defaultStringLength(191);
        $appSetting = Schema::hasTable('settings') ? Setting::query()->first() : null;
        View::share('appSetting', $appSetting);

        if (app()->isLocal()) {
            Model::preventLazyLoading();
        }
    }
}
