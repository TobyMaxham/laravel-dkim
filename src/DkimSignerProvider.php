<?php

namespace TobyMaxham\LaravelDkimSigner;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class DkimSignerProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->booting(function () {
            Event::listen(
                \Illuminate\Mail\Events\MessageSending::class,
                \TobyMaxham\LaravelDkimSigner\Listener\ApplyDkimSignature::class
            );
        });

        $this->mergeConfigFrom(
            __DIR__.'/../config/laravel-dkim.php', 'laravel-dkim'
        );
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/laravel-dkim.php' => config_path('laravel-dkim.php'),
        ]);

        $this->app->bind(DkimSigner::class, fn ($app) => new DkimSigner($app));
    }
}
