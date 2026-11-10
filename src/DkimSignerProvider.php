<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelDkimSigner;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Mail\Events\MessageSending;
use TobyMaxham\LaravelDkimSigner\Config\SenderResolver;
use TobyMaxham\LaravelDkimSigner\Listener\ApplyDkimSignature;

class DkimSignerProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-dkim.php', 'laravel-dkim');

        $this->app->scoped(SenderResolver::class);
        $this->app->scoped(DkimSigner::class);
    }

    public function boot(): void
    {
        Event::listen(MessageSending::class, ApplyDkimSignature::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/laravel-dkim.php' => config_path('laravel-dkim.php'),
            ], 'laravel-dkim-config');
        }
    }
}
