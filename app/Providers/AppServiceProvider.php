<?php

namespace App\Providers;

use App\Models\DiningTable;
use App\Models\ProductionBatch;
use App\Models\ProductionOrder;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useTailwind();

        Route::model('table', DiningTable::class);
        Route::model('opname', StockOpname::class);
        Route::model('transfer', StockTransfer::class);
        Route::model('production', ProductionOrder::class);
        Route::model('batch', ProductionBatch::class);

        Gate::before(function (User $user, string $ability) {
            if ($user->isSuperAdmin()) {
                return true;
            }

            if ($user->hasPermission($ability)) {
                return true;
            }

            return null;
        });

        // Apache docroot is the project folder, so the app lives under /public.
        if (! $this->app->runningInConsole()) {
            URL::forceRootUrl(rtrim(request()->getSchemeAndHttpHost().request()->getBasePath(), '/'));
        }

        config(['livewire.asset_url' => url('/livewire/livewire.js')]);
    }
}
