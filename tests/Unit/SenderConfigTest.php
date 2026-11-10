<?php

declare(strict_types=1);

use TobyMaxham\LaravelDkimSigner\Config\SenderConfig;
use TobyMaxham\LaravelDkimSigner\Exceptions\InvalidDkimConfiguration;

function senderConfig(array $config): SenderConfig
{
    return SenderConfig::fromArray('main', $config + ['dkim_selector' => 'default']);
}

it('reads a valid config', function () {
    $sender = senderConfig([
        'dkim_selector'    => ' Mail-2025 ',
        'dkim_domain'      => 'Example.COM',
        'dkim_private_key' => '/keys/key.pem',
        'dkim_algo'        => 'rsa-sha256',
        'dkim_passphrase'  => ' secret ',
        'addresses'        => ['Info@Example.com', 'info@example.com', ''],
        'options'          => ['header_canon' => 'simple', 'body_canon' => 'relaxed', 'signature_expiration_delay' => 3600, 'headers_to_ignore' => ['X-Debug']],
    ]);

    expect($sender)
        ->name->toBe('main')
        ->selector->toBe('mail-2025')
        ->domain->toBe('example.com')
        ->privateKey->toBe('/keys/key.pem')
        ->passphrase->toBe(' secret ')
        ->algorithm->toBe('rsa-sha256')
        ->addresses->toBe(['info@example.com'])
        ->options->toBe(['header_canon' => 'simple', 'body_canon' => 'relaxed', 'signature_expiration_delay' => 3600, 'headers_to_ignore' => ['X-Debug']])
        ->and($sender->dnsName())->toBe('mail-2025._domainkey.example.com')
        ->and($sender->configKey('dkim_selector'))->toBe('laravel-dkim.sender.main.dkim_selector');
});

it('uses defaults for empty values', function () {
    $sender = senderConfig(['dkim_domain' => '', 'dkim_private_key' => '', 'dkim_passphrase' => '', 'dkim_algo' => null]);

    expect($sender)
        ->domain->toBeNull()
        ->privateKey->toBeNull()
        ->passphrase->toBeNull()
        ->algorithm->toBe('rsa-sha256')
        ->addresses->toBe([])
        ->options->toBe([])
        ->and($sender->dnsName())->toBeNull()
        ->and($sender->withDomain('Example.org')->dnsName())->toBe('default._domainkey.example.org');
});

it('ignores the removed dkim_identity', function () {
    expect(senderConfig(['dkim_identity' => 'info@example.com']))->toBeInstanceOf(SenderConfig::class);
});

it('reads addresses from a comma separated string', function () {
    expect(senderConfig(['addresses' => 'a@example.com, B@example.com'])->addresses)->toBe(['a@example.com', 'b@example.com']);
});

it('builds the default key path', function () {
    expect(SenderConfig::defaultKeyPath('Example.com', 'Mail'))->toBe(storage_path('app/dkim/example.com.mail.pem'));
});

it('rejects invalid values with the config key in the message', function (array $config, string $key) {
    expect(fn () => senderConfig($config))->toThrow(function (InvalidDkimConfiguration $e) use ($key) {
        expect($e->configKey)->toBe('laravel-dkim.sender.main.'.$key)
            ->and($e->getMessage())->toStartWith('Invalid DKIM configuration [laravel-dkim.sender.main.'.$key.']: ');
    });
})->with([
    'missing selector'            => [['dkim_selector' => null], 'dkim_selector'],
    'empty selector'              => [['dkim_selector' => ' '], 'dkim_selector'],
    'selector with underscore'    => [['dkim_selector' => 'my_selector'], 'dkim_selector'],
    'selector with leading dash'  => [['dkim_selector' => '-mail'], 'dkim_selector'],
    'selector not a string'       => [['dkim_selector' => 1], 'dkim_selector'],
    'domain without dot'          => [['dkim_domain' => 'localhost'], 'dkim_domain'],
    'domain with scheme'          => [['dkim_domain' => 'https://example.com'], 'dkim_domain'],
    'domain with address'         => [['dkim_domain' => 'info@example.com'], 'dkim_domain'],
    'domain too long'             => [['dkim_domain' => str_repeat('a.', 127).'com'], 'dkim_domain'],
    'unsupported algorithm'       => [['dkim_algo' => 'ed25519-sha256'], 'dkim_algo'],
    'unknown algorithm'           => [['dkim_algo' => 'rsa-sha1'], 'dkim_algo'],
    'key not a string'            => [['dkim_private_key' => ['x']], 'dkim_private_key'],
    'addresses not a list'        => [['addresses' => 5], 'addresses'],
    'invalid address'             => [['addresses' => ['nope']], 'addresses'],
    'address not a string'        => [['addresses' => [5]], 'addresses'],
    'options not an array'        => [['options' => 'simple'], 'options'],
    'unknown option'              => [['options' => ['body_show_length' => true]], 'options.body_show_length'],
    'invalid header canon'        => [['options' => ['header_canon' => 'nowsp']], 'options.header_canon'],
    'invalid body canon'          => [['options' => ['body_canon' => 1]], 'options.body_canon'],
    'negative expiration'         => [['options' => ['signature_expiration_delay' => -1]], 'options.signature_expiration_delay'],
    'expiration not an int'       => [['options' => ['signature_expiration_delay' => '60']], 'options.signature_expiration_delay'],
    'headers to ignore no list'   => [['options' => ['headers_to_ignore' => 'X-A']], 'options.headers_to_ignore'],
    'headers to ignore no string' => [['options' => ['headers_to_ignore' => [1]]], 'options.headers_to_ignore'],
]);

it('never puts the key or the passphrase into the message', function () {
    try {
        senderConfig(['dkim_selector' => 'in valid', 'dkim_private_key' => '-----BEGIN PRIVATE KEY-----SECRET', 'dkim_passphrase' => 'top-secret']);
    } catch (InvalidDkimConfiguration $e) {
        expect($e->getMessage())->not->toContain('SECRET')->not->toContain('top-secret');

        return;
    }

    $this->fail('No exception thrown.');
});
