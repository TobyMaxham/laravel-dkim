<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use TobyMaxham\LaravelDkimSigner\Dns\DnsResolver;
use TobyMaxham\LaravelDkimSigner\Config\SenderConfig;
use TobyMaxham\LaravelDkimSigner\Tests\Fixtures\Keys;
use TobyMaxham\LaravelDkimSigner\Tests\Fixtures\FakeDnsResolver;

beforeEach(function () {
    config()->set('laravel-dkim.dkim_signer_enabled', true);
    config()->set('laravel-dkim.sender', [
        'default' => ['dkim_selector' => 'mail', 'dkim_domain' => 'example.com', 'dkim_private_key' => Keys::file()],
    ]);
});

function fakeDns(array $records): FakeDnsResolver
{
    app()->instance(DnsResolver::class, $dns = new FakeDnsResolver($records));

    return $dns;
}

function check(array $parameters = []): array
{
    $code = Artisan::call('dkim:check', $parameters);

    return [$code, Artisan::output()];
}

it('reports OK when the DNS record matches', function () {
    $dns = fakeDns(['mail._domainkey.example.com' => ['v=DKIM1; k=rsa; p='.Keys::dnsValue()]]);

    [$code, $output] = check();

    expect($code)->toBe(0)
        ->and($output)->toMatch('/default\s+\|\s+example\.com\s+\|\s+mail\s+\|\s+OK\s+\|\s+Key is valid and matches the DNS record at mail\._domainkey\.example\.com\./')
        ->and($dns->lookups)->toBe(['mail._domainkey.example.com']);
});

it('accepts a record without v= and k= and with spaces in p=', function () {
    fakeDns(['mail._domainkey.example.com' => ['p='.chunk_split(Keys::dnsValue(), 64, ' ')]]);

    expect(check()[0])->toBe(0);
});

it('reports DNS problems', function (array $records, string $status, string $details) {
    fakeDns(['mail._domainkey.example.com' => $records]);

    [$code, $output] = check();

    expect($code)->toBe(1)
        ->and($output)->toContain($status)
        ->and($output)->toContain($details);
})->with([
    'missing'       => [[], 'DNS_MISSING', 'No TXT record found at mail._domainkey.example.com.'],
    'mismatch'      => [['v=DKIM1; k=rsa; p='.Keys::dnsValue('other')], 'DNS_MISMATCH', 'does not match the private key'],
    'no DKIM'       => [['google-site-verification=abc'], 'DNS_INVALID', 'is no DKIM record'],
    'two records'   => [['v=DKIM1; p='.Keys::dnsValue(), 'v=DKIM1; p='.Keys::dnsValue('other')], 'DNS_INVALID', 'more than one DKIM record'],
    'wrong version' => [['v=DKIM2; p='.Keys::dnsValue()], 'DNS_INVALID', 'The record must start with "v=DKIM1".'],
    'wrong type'    => [['v=DKIM1; k=ed25519; p='.Keys::dnsValue()], 'DNS_INVALID', 'The key type (k=) must be "rsa".'],
    'revoked'       => [['v=DKIM1; k=rsa; p='], 'DNS_INVALID', 'the key is revoked'],
    'no base64'     => [['v=DKIM1; k=rsa; p=!!!'], 'DNS_INVALID', 'is not valid base64'],
]);

it('reports key errors', function () {
    config()->set('laravel-dkim.sender.default.dkim_private_key', '/missing.pem');
    fakeDns([]);

    [$code, $output] = check();

    expect($code)->toBe(1)->and($output)->toContain('KEY_ERROR')->toContain('/missing.pem');
});

it('reports config errors per sender', function (mixed $config) {
    config()->set('laravel-dkim.sender.broken', $config);
    fakeDns(['mail._domainkey.example.com' => ['v=DKIM1; k=rsa; p='.Keys::dnsValue()]]);

    [$code, $output] = check();

    expect($code)->toBe(1)
        ->and($output)->toContain('CONFIG_ERROR')
        ->and($output)->toMatch('/default\s+\|\s+example\.com\s+\|\s+mail\s+\|\s+OK/');
})->with([
    'invalid selector' => [['dkim_selector' => 'in valid']],
    'not an array'     => ['nope'],
]);

it('reports invalid global config', function (array $config, string $message) {
    config()->set($config);
    fakeDns(['mail._domainkey.example.com' => ['v=DKIM1; k=rsa; p='.Keys::dnsValue()]]);

    [$code, $output] = check();

    expect($code)->toBe(1)->and($output)->toContain($message);
})->with([
    'fallback'      => [['laravel-dkim.dkim_fallback' => 'never'], '[laravel-dkim.dkim_fallback]'],
    'on error'      => [['laravel-dkim.dkim_on_error' => 'never'], '[laravel-dkim.dkim_on_error]'],
    'two wildcards' => [['laravel-dkim.sender.w1' => ['dkim_selector' => 'a'], 'laravel-dkim.sender.w2' => ['dkim_selector' => 'b']], 'Only one sender may have an empty dkim_domain, found: w1, w2.'],
]);

it('skips DNS with --no-dns', function () {
    $dns = fakeDns([]);

    [$code, $output] = check(['--no-dns' => true]);

    expect($code)->toBe(0)
        ->and($output)->toContain('Key is valid, DNS not checked (--no-dns).')
        ->and($dns->lookups)->toBe([]);
});

it('skips a sender without domain and key', function () {
    config()->set('laravel-dkim.sender.default.dkim_domain', null);
    config()->set('laravel-dkim.sender.default.dkim_private_key', null);
    fakeDns([]);

    [$code, $output] = check();

    expect($code)->toBe(0)->and($output)->toContain('SKIPPED')->toContain('pass --domain=<domain> to check this sender');
});

it('checks only the key of a sender without domain', function () {
    config()->set('laravel-dkim.sender.default.dkim_domain', null);
    $dns = fakeDns([]);

    [$code, $output] = check();

    expect($code)->toBe(0)
        ->and($output)->toContain('(From domain)')
        ->and($output)->toContain('Key is valid. No dkim_domain: pass --domain=<domain> to check the DNS record.')
        ->and($dns->lookups)->toBe([]);
});

it('checks a sender without domain with --domain', function () {
    config()->set('laravel-dkim.sender.default.dkim_domain', null);
    config()->set('laravel-dkim.sender.default.dkim_private_key', null);
    $path = SenderConfig::defaultKeyPath('example.org', 'mail');
    @mkdir(dirname($path), 0700, true);
    file_put_contents($path, Keys::pair()['private']);
    fakeDns(['mail._domainkey.example.org' => ['v=DKIM1; k=rsa; p='.Keys::dnsValue()]]);

    try {
        [$code, $output] = check(['--domain' => 'example.org']);
    } finally {
        unlink($path);
    }

    expect($code)->toBe(0)->and($output)->toContain('matches the DNS record at mail._domainkey.example.org');
});

it('ignores --domain for senders with domain', function () {
    $dns = fakeDns(['mail._domainkey.example.com' => ['v=DKIM1; k=rsa; p='.Keys::dnsValue()]]);

    expect(check(['--domain' => 'example.org'])[0])->toBe(0)
        ->and($dns->lookups)->toBe(['mail._domainkey.example.com']);
});

it('rejects an invalid --domain', function () {
    config()->set('laravel-dkim.sender.default.dkim_domain', null);
    fakeDns([]);

    [$code, $output] = check(['--domain' => 'nope']);

    expect($code)->toBe(1)->and($output)->toContain('CONFIG_ERROR')->toContain('"nope" is not a valid domain name.');
});

it('checks one sender', function () {
    config()->set('laravel-dkim.sender.other', ['dkim_selector' => 'x', 'dkim_domain' => 'example.org', 'dkim_private_key' => '/missing.pem']);
    fakeDns(['mail._domainkey.example.com' => ['v=DKIM1; k=rsa; p='.Keys::dnsValue()]]);

    [$code, $output] = check(['sender' => 'default']);

    expect($code)->toBe(0)->and($output)->not->toContain('example.org');
});

it('rejects an unknown sender', function () {
    [$code, $output] = check(['sender' => 'missing']);

    expect($code)->toBe(1)->and($output)->toContain('The sender "missing" does not exist in laravel-dkim.sender.');
});

it('warns when signing is disabled', function () {
    config()->set('laravel-dkim.dkim_signer_enabled', false);
    fakeDns(['mail._domainkey.example.com' => ['v=DKIM1; k=rsa; p='.Keys::dnsValue()]]);

    [$code, $output] = check();

    expect($code)->toBe(0)->and($output)->toContain('Signing is disabled. Set MAIL_DKIM_ENABLED=true');
});

it('never prints the key or the passphrase', function () {
    config()->set('laravel-dkim.sender.default.dkim_private_key', Keys::file('enc', 'secret'));
    config()->set('laravel-dkim.sender.default.dkim_passphrase', 'wrong-secret');
    fakeDns([]);

    [$code, $output] = check();

    expect($code)->toBe(1)
        ->and($output)->toContain('The passphrase of the private key is wrong.')
        ->not->toContain('secret')
        ->not->toContain('PRIVATE KEY');
});
