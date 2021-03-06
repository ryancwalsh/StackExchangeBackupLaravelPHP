<?php

namespace ryancwalsh\StackExchangeBackupLaravel;

use Illuminate\Support\ServiceProvider;

class ExportStackExchangeServiceProvider extends ServiceProvider {

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot() {
        $this->publishes([
            __DIR__ . '/stackapps.php' => config_path('stackapps.php'), //https://laravel.com/docs/5.6/packages#configuration
        ]);
        if ($this->app->runningInConsole()) {
            $this->commands([
                ExportStackExchangeCommand::class, //https://laravel.com/docs/5.6/packages#commands https://stackoverflow.com/a/52228290/470749
            ]);
        }
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register() {
        
    }

}
