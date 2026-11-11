<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelDkimSigner\Tests\Fixtures;

use RuntimeException;

/**
 * Creates RSA key pairs at runtime, so no key file is stored in the repository.
 */
final class Keys
{
    /** @var array<string, array{private: string, public: string}> */
    private static array $pairs = [];

    /** @var list<string> */
    private static array $files = [];

    /**
     * @return array{private: string, public: string}
     */
    public static function pair(string $name = 'default', ?string $passphrase = null): array
    {
        $cacheKey = $name.'|'.$passphrase;

        if (! isset(self::$pairs[$cacheKey])) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

            if (false === $key || ! openssl_pkey_export($key, $private, $passphrase)) {
                throw new RuntimeException('Unable to create a test key.');
            }

            $details = openssl_pkey_get_details($key);

            self::$pairs[$cacheKey] = ['private' => $private, 'public' => (string) ($details['key'] ?? '')];
        }

        return self::$pairs[$cacheKey];
    }

    /**
     * Write the private key of a pair to a temporary file and return the path.
     */
    public static function file(string $name = 'default', ?string $passphrase = null): string
    {
        $path = self::tempDir().'/'.bin2hex(random_bytes(6)).'.pem';

        file_put_contents($path, self::pair($name, $passphrase)['private']);
        chmod($path, 0600);

        return self::$files[] = $path;
    }

    /**
     * Base64 of the DER encoded public key, the value of "p=" in the DNS record.
     */
    public static function dnsValue(string $name = 'default', ?string $passphrase = null): string
    {
        $pem = self::pair($name, $passphrase)['public'];

        return (string) preg_replace('/-----[^-]+-----|\s+/', '', $pem);
    }

    public static function tempDir(): string
    {
        $dir = sys_get_temp_dir().'/laravel-dkim-tests';

        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        return $dir;
    }

    public static function cleanup(): void
    {
        foreach (self::$files as $file) {
            @unlink($file);
        }

        self::$files = [];
    }
}
