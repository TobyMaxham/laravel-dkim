<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelDkimSigner\Key;

use OpenSSLAsymmetricKey;
use TobyMaxham\LaravelDkimSigner\Config\SenderConfig;
use TobyMaxham\LaravelDkimSigner\Exceptions\InvalidDkimConfiguration;

/**
 * The loaded and checked private key of a sender.
 */
final readonly class PrivateKey
{
    private function __construct(
        public string $pem,
        public OpenSSLAsymmetricKey $key,
    ) {}

    /**
     * Load the key of a sender: a PEM string, a file path or the default path
     * storage/app/dkim/{domain}.{selector}.pem.
     */
    public static function load(SenderConfig $sender): self
    {
        $configKey = $sender->configKey('dkim_private_key');
        $pem = self::read($sender, $configKey);

        $key = openssl_pkey_get_private($pem, $sender->passphrase ?? '');
        self::clearOpenSslErrors();

        if (false === $key) {
            throw InvalidDkimConfiguration::forKey(
                str_contains($pem, 'ENCRYPTED') ? $sender->configKey('dkim_passphrase') : $configKey,
                match (true) {
                    ! str_contains($pem, 'ENCRYPTED') => 'The private key is not readable. It must be an RSA private key in PEM format.',
                    null === $sender->passphrase      => 'The private key is encrypted, but no passphrase is set.',
                    default                           => 'The passphrase of the private key is wrong.',
                },
            );
        }

        if (OPENSSL_KEYTYPE_RSA !== (openssl_pkey_get_details($key)['type'] ?? null)) {
            throw InvalidDkimConfiguration::forKey($configKey, 'The private key must be an RSA key.');
        }

        return new self($pem, $key);
    }

    /**
     * Base64 of the DER encoded public key, the value of "p=" in the DNS record.
     */
    public function publicKey(): string
    {
        return self::publicKeyOf($this->key);
    }

    public static function publicKeyOf(OpenSSLAsymmetricKey $key): string
    {
        $pem = (string) (openssl_pkey_get_details($key)['key'] ?? '');

        return (string) preg_replace('/-----[^-]+-----|\s+/', '', $pem);
    }

    private static function read(SenderConfig $sender, string $configKey): string
    {
        $value = $sender->privateKey;

        if (null !== $value && str_starts_with(ltrim($value), '-----BEGIN')) {
            return trim($value);
        }

        if (null === $value && null === $sender->domain) {
            throw InvalidDkimConfiguration::forKey($configKey, 'No private key is set and the default path needs a domain.');
        }

        $path = $value ?? SenderConfig::defaultKeyPath((string) $sender->domain, $sender->selector);

        if (! is_file($path) || ! is_readable($path)) {
            throw InvalidDkimConfiguration::forKey($configKey, sprintf('The private key file "%s" does not exist or is not readable.', $path));
        }

        return (string) file_get_contents($path);
    }

    private static function clearOpenSslErrors(): void
    {
        while (false !== openssl_error_string()) {
            // drain the error queue, so later calls do not report old errors
        }
    }
}
