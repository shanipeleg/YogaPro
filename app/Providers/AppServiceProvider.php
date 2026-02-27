<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Share queue counts to all admin views (for the nav badge)
        View::composer('layouts.admin', function ($view) {
            $view->with('navPendingJobs', DB::table('jobs')->count());
            $view->with('navFailedJobs', DB::table('failed_jobs')->count());
        });
    }
}
