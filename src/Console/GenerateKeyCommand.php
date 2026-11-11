<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelDkimSigner\Console;

use Illuminate\Console\Command;
use TobyMaxham\LaravelDkimSigner\Key\PrivateKey;
use Symfony\Component\Console\Attribute\AsCommand;
use TobyMaxham\LaravelDkimSigner\Config\SenderConfig;

#[AsCommand(name: 'dkim:generate')]
final class GenerateKeyCommand extends Command
{
    protected $signature = 'dkim:generate
        {domain? : The domain of your From addresses (default: dkim_domain of the default sender)}
        {--selector= : The DKIM selector (default: dkim_selector of the default sender)}
        {--bits=2048 : The key size in bits (1024 to 4096)}
        {--path= : Where to store the private key (default: storage/app/dkim/DOMAIN.SELECTOR.pem)}
        {--passphrase : Ask for a passphrase to encrypt the private key}
        {--force : Overwrite an existing private key}';

    protected $description = 'Create a DKIM key pair and show the DNS record';

    public function handle(): int
    {
        $default = (array) config('laravel-dkim.sender.'.config('laravel-dkim.dkim_default_sender', 'default'), []);

        $domain = strtolower(trim((string) ($this->argument('domain') ?? $default['dkim_domain'] ?? '')));
        $selector = strtolower(trim((string) ($this->option('selector') ?? $default['dkim_selector'] ?? 'default')));
        $bits = filter_var($this->option('bits'), FILTER_VALIDATE_INT);

        if ('' === $domain) {
            return $this->failWith('Pass the domain of your From addresses, e.g. php artisan dkim:generate example.com');
        }

        if (! SenderConfig::isValidDomain($domain)) {
            return $this->failWith(sprintf('"%s" is not a valid domain name.', $domain));
        }

        if (! SenderConfig::isValidSelector($selector)) {
            return $this->failWith(sprintf('"%s" is not a valid selector. Use letters, digits and "-", e.g. "default" or "mail-2025".', $selector));
        }

        if (false === $bits || $bits < 1024 || $bits > 4096) {
            return $this->failWith('The key size must be between 1024 and 4096 bits.');
        }

        $path = $this->option('path');
        $path = is_string($path) && '' !== $path ? $path : SenderConfig::defaultKeyPath($domain, $selector);

        if (file_exists($path) && ! $this->option('force')) {
            return $this->failWith(sprintf('The file "%s" already exists. Use --force to overwrite it. Then the DNS record must be updated as well.', $path));
        }

        $passphrase = $this->option('passphrase') ? $this->askPassphrase() : null;

        if (false === $passphrase) {
            return $this->failWith('The passphrases are empty or do not match.');
        }

        [$pem, $publicKey] = $this->createKey($bits, $passphrase);

        if (! $this->writeKey($path, $pem)) {
            return $this->failWith(sprintf('Unable to write the private key to "%s".', $path));
        }

        $this->output($domain, $selector, $path, $publicKey, $passphrase, $bits);

        return self::SUCCESS;
    }

    /**
     * @return array{string, string} the private key as PEM and the public key for DNS
     */
    private function createKey(int $bits, ?string $passphrase): array
    {
        $key = openssl_pkey_new(['private_key_bits' => $bits, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        if (false === $key || ! openssl_pkey_export($key, $pem, $passphrase, ['encrypt_key_cipher' => OPENSSL_CIPHER_AES_256_CBC])) {
            throw new \RuntimeException('Unable to create the key pair with OpenSSL.'); // @codeCoverageIgnore
        }

        return [(string) $pem, PrivateKey::publicKeyOf($key)];
    }

    private function writeKey(string $path, string $pem): bool
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0700, true)) {
            return false;
        }

        $umask = umask(0077);

        try {
            if (false === @file_put_contents($path, $pem)) {
                return false;
            }
        } finally {
            umask($umask);
        }

        return chmod($path, 0600);
    }

    private function askPassphrase(): string|false
    {
        $passphrase = (string) $this->secret('Passphrase for the private key');

        if ('' === $passphrase || $passphrase !== (string) $this->secret('Repeat the passphrase')) {
            return false;
        }

        return $passphrase;
    }

    private function output(string $domain, string $selector, string $path, string $publicKey, ?string $passphrase, int $bits): void
    {
        $record = 'v=DKIM1; k=rsa; p='.$publicKey;

        $this->components->info(sprintf('Private key written to %s (mode 0600). Never commit it.', $path));

        if ($this->isPublic($path)) {
            $this->components->warn('The private key is in the public directory. Move it to a place that is not reachable from the web.');
        }

        if ($bits < 2048) {
            $this->components->warn('Keys with less than 2048 bits are not recommended.');
        }

        $this->line('Add this TXT record to the DNS of '.$domain.':');
        $this->newLine();
        $this->line('  Name:  '.$selector.'._domainkey.'.$domain);
        $this->line('  Value: '.$record);
        $this->newLine();
        $this->line('If your DNS provider only accepts strings up to 255 characters, enter the value as these strings:');
        $this->newLine();
        $this->line('  '.implode(' ', array_map(fn (string $chunk) => '"'.$chunk.'"', str_split($record, 255))));
        $this->newLine();
        $this->line('Add these values to your .env:');
        $this->newLine();
        $this->line('  MAIL_DKIM_ENABLED=true');
        $this->line('  MAIL_DKIM_DOMAIN='.$domain);
        $this->line('  MAIL_DKIM_SELECTOR='.$selector);

        if ($this->option('path')) {
            $this->line('  MAIL_DKIM_PRIVATE_KEY='.$path);
        }

        if (null !== $passphrase) {
            $this->line('  MAIL_DKIM_PASSPHRASE=<your passphrase>');
        }

        $this->newLine();
        $this->line('Run "php artisan dkim:check" when the DNS record is published.');
    }

    private function isPublic(string $path): bool
    {
        $public = realpath(public_path());
        $directory = realpath(dirname($path));

        return false !== $public && false !== $directory
            && ($directory === $public || str_starts_with($directory, $public.DIRECTORY_SEPARATOR));
    }

    private function failWith(string $message): int
    {
        $this->components->error($message);

        return self::FAILURE;
    }
}
