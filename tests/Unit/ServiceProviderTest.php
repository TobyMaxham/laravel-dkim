<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use TobyMaxham\LaravelDkimSigner\DkimSigner;
use TobyMaxham\LaravelDkimSigner\DkimSignerProvider;

it('merges the package config', function () {
    expect(config('laravel-dkim'))->toMatchArray([
        'dkim_signer_enabled' => false,
        'dkim_default_sender' => 'default',
        'dkim_fallback'       => 'skip',
        'dkim_on_error'       => 'throw',
    ])->and(config('laravel-dkim.sender.default'))->toBe([
        'dkim_selector'    => 'default',
        'dkim_domain'      => null,
        'dkim_private_key' => null,
        'dkim_algo'        => 'rsa-sha256',
        'dkim_passphrase'  => null,
        'addresses'        => [],
        'options'          => [],
    ]);
});

it('binds the signer as scoped instance', function () {
    $signer = app(DkimSigner::class);

    expect(app(DkimSigner::class))->toBe($signer);

    app()->forgetScopedInstances();

    expect(app(DkimSigner::class))->not->toBe($signer);
});

it('publishes the config file with a tag', function () {
    $paths = ServiceProvider::pathsToPublish(DkimSignerProvider::class, 'laravel-dkim-config');

    expect($paths)->toHaveCount(1)
        ->and(array_values($paths)[0])->toBe(config_path('laravel-dkim.php'))
        ->and(array_keys($paths)[0])->toEndWith('config/laravel-dkim.php');
});
