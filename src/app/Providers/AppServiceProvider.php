<?php

namespace App\Providers;

use App\Contracts\ContactServiceInterface;
use App\Contracts\ImportServiceInterface;
use App\Services\BaboonImportService;
use App\Services\ContactService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ContactServiceInterface::class, ContactService::class);
        $this->app->bind(ImportServiceInterface::class, BaboonImportService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
