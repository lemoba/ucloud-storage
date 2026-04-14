<?php

namespace UCloud\Storage;

use Illuminate\Support\ServiceProvider;

class UCloudServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/ucloud.php', 'ucloud'
        );

        $this->app->singleton('ucloud', function ($app) {
            return new UCloudClient($app['config']['ucloud']);
        });
        
        $this->app->alias('ucloud', UCloudClient::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/ucloud.php' => config_path('ucloud.php'),
            ], 'ucloud-config');
        }
    }
}
