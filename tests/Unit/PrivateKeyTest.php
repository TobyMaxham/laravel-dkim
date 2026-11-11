<?php

declare(strict_types=1);

use TobyMaxham\LaravelDkimSigner\Key\PrivateKey;
use TobyMaxham\LaravelDkimSigner\Config\SenderConfig;
use TobyMaxham\LaravelDkimSigner\Tests\Fixtures\Keys;
use TobyMaxham\LaravelDkimSigner\Exceptions\InvalidDkimConfiguration;

function keySender(?string $key, ?string $passphrase = null, ?string $domain = 'example.com', string $selector = 'default'): SenderConfig
{
    return new SenderConfig('default', $selector, $domain, $key, $passphrase);
}

function expectKeyError(SenderConfig $sender, string $configKey, string $message): void
{
    expect(fn () => PrivateKey::load($sender))->toThrow(function (InvalidDkimConfiguration $e) use ($configKey, $message) {
        expect($e->configKey)->toBe($configKey)
            ->and($e->getMessage())->toContain($message)
            ->not->toContain('PRIVATE KEY')
            ->not->toContain('secret');
    });
}

it('loads a key from a file', function () {
    expect(PrivateKey::load(keySender(Keys::file()))->publicKey())->toBe(Keys::dnsValue());
});

it('loads a key from a PEM string', function () {
    expect(PrivateKey::load(keySender("\n".Keys::pair()['private']))->publicKey())->toBe(Keys::dnsValue());
});

it('loads an encrypted key with the passphrase', function () {
    expect(PrivateKey::load(keySender(Keys::file('enc', 'secret'), 'secret'))->publicKey())->toBe(Keys::dnsValue('enc', 'secret'));
});

it('loads the key from the default path', function () {
    $path = SenderConfig::defaultKeyPath('example.com', 'mail');
    @mkdir(dirname($path), 0700, true);
    file_put_contents($path, Keys::pair()['private']);

    try {
        expect(PrivateKey::load(keySender(null, selector: 'mail'))->publicKey())->toBe(Keys::dnsValue());
    } finally {
        unlink($path);
    }
});

it('reports a missing file', function () {
    expectKeyError(keySender('/does/not/exist.pem'), 'laravel-dkim.sender.default.dkim_private_key', 'The private key file "/does/not/exist.pem" does not exist or is not readable.');
});

it('reports a missing default file', function () {
    expectKeyError(keySender(null), 'laravel-dkim.sender.default.dkim_private_key', 'app/dkim/example.com.default.pem" does not exist');
});

it('reports a missing key without domain', function () {
    expectKeyError(keySender(null, domain: null), 'laravel-dkim.sender.default.dkim_private_key', 'No private key is set and the default path needs a domain.');
});

it('reports a file that is no key', function () {
    $path = Keys::tempDir().'/no-key.pem';
    file_put_contents($path, 'hello');

    try {
        expectKeyError(keySender($path), 'laravel-dkim.sender.default.dkim_private_key', 'The private key is not readable.');
    } finally {
        unlink($path);
    }
});

it('reports a broken PEM string', function () {
    expectKeyError(keySender("-----BEGIN PRIVATE KEY-----\nnope\n-----END PRIVATE KEY-----"), 'laravel-dkim.sender.default.dkim_private_key', 'The private key is not readable.');
});

it('reports a missing passphrase', function () {
    expectKeyError(keySender(Keys::file('enc', 'secret')), 'laravel-dkim.sender.default.dkim_passphrase', 'The private key is encrypted, but no passphrase is set.');
});

it('reports a wrong passphrase', function () {
    expectKeyError(keySender(Keys::file('enc', 'secret'), 'wrong'), 'laravel-dkim.sender.default.dkim_passphrase', 'The passphrase of the private key is wrong.');
});

it('reports a key that is no RSA key', function () {
    // PHP 8.3 checks private_key_bits for EC keys too
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1', 'private_key_bits' => 384]);
    openssl_pkey_export($key, $pem);

    expectKeyError(keySender($pem), 'laravel-dkim.sender.default.dkim_private_key', 'The private key must be an RSA key.');
});

it('leaves no OpenSSL errors behind', function () {
    try {
        PrivateKey::load(keySender(Keys::file('enc', 'secret'), 'wrong'));
    } catch (InvalidDkimConfiguration) {
    }

    expect(openssl_error_string())->toBeFalse();
});
