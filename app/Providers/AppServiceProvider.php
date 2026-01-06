<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */

    public function register()
    {
        $this->app->bind(
            \App\Repositories\ComplaintRepository::class,
            function ($app) {
                return new \App\Repositories\ComplaintRepository(new \App\Models\Complaint());
            }
        );
    }


    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('local') || app()->environment('testing')) {
            \DB::listen(function ($query) {
                \Log::channel('jmeter')->debug('Query Executed', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time
                ]);
            });
        }
    }
}
