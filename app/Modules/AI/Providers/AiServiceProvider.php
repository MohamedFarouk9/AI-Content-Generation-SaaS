<?php

namespace App\Modules\AI\Providers;

use App\Modules\AI\AiManager;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // register the ai manager as a singleton
        $this->app->singleton(AiManager::class, function ($app) {
            return new AiManager();
        });
    }

    public function boot(): void
    {
        // Register configuration or commands here if needed in the future
    }
}
