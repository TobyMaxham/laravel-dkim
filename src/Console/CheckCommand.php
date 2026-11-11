<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelDkimSigner\Console;

use Illuminate\Console\Command;
use TobyMaxham\LaravelDkimSigner\DkimSigner;
use TobyMaxham\LaravelDkimSigner\Key\PrivateKey;
use TobyMaxham\LaravelDkimSigner\Dns\DnsResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use TobyMaxham\LaravelDkimSigner\Config\SenderConfig;
use TobyMaxham\LaravelDkimSigner\Config\SenderResolver;
use TobyMaxham\LaravelDkimSigner\Exceptions\InvalidDkimConfiguration;

#[AsCommand(name: 'dkim:check')]
final class CheckCommand extends Command
{
    public const string OK = 'OK';

    public const string SKIPPED = 'SKIPPED';

    public const string CONFIG_ERROR = 'CONFIG_ERROR';

    public const string KEY_ERROR = 'KEY_ERROR';

    public const string DNS_MISSING = 'DNS_MISSING';

    public const string DNS_MISMATCH = 'DNS_MISMATCH';

    public const string DNS_INVALID = 'DNS_INVALID';

    protected $signature = 'dkim:check
        {sender? : The name of the sender to check (default: all senders)}
        {--domain= : The domain to check for a sender without dkim_domain}
        {--no-dns : Only check the config and the private key}';

    protected $description = 'Check the DKIM config, the private keys and the DNS records';

    public function handle(SenderResolver $resolver, DkimSigner $signer, DnsResolver $dns): int
    {
        $failed = ! $this->checkGlobalConfig($resolver, $signer);

        $senders = (array) config('laravel-dkim.sender', []);
        $only = $this->argument('sender');
        $only = is_string($only) ? $only : null;

        if (null !== $only) {
            if (! array_key_exists($only, $senders)) {
                $this->components->error(sprintf('The sender "%s" does not exist in laravel-dkim.sender.', $only));

                return self::FAILURE;
            }

            $senders = [$only => $senders[$only]];
        }

        $rows = [];

        foreach ($senders as $name => $config) {
            $row = $this->checkSender((string) $name, is_array($config) ? $config : null, $dns);
            $failed = $failed || ! in_array($row[3], [self::OK, self::SKIPPED], true);
            $rows[] = $row;
        }

        $this->table(['Sender', 'Domain', 'Selector', 'Status', 'Details'], $rows);

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function checkGlobalConfig(SenderResolver $resolver, DkimSigner $signer): bool
    {
        if (! $signer->isEnabled()) {
            $this->components->warn('Signing is disabled. Set MAIL_DKIM_ENABLED=true to sign your mails.');
        }

        try {
            $resolver->fallbackMode();
            $signer->errorMode();
            $resolver->senders();
        } catch (InvalidDkimConfiguration $e) {
            $this->components->error($e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * @param  array<array-key, mixed>|null  $config
     * @return array{string, string, string, string, string}
     */
    private function checkSender(string $name, ?array $config, DnsResolver $dns): array
    {
        try {
            $sender = SenderConfig::fromArray($name, $config ?? throw InvalidDkimConfiguration::forKey('laravel-dkim.sender.'.$name, 'The value must be an array.'));
        } catch (InvalidDkimConfiguration $e) {
            return [$name, '', '', self::CONFIG_ERROR, $e->getMessage()];
        }

        $domain = $this->option('domain');

        if (null === $sender->domain && is_string($domain) && '' !== $domain) {
            if (! SenderConfig::isValidDomain($domain)) {
                return [$name, $domain, $sender->selector, self::CONFIG_ERROR, sprintf('"%s" is not a valid domain name.', $domain)];
            }

            $sender = $sender->withDomain($domain);
        }

        $row = fn (string $status, string $details): array => [$name, $sender->domain ?? '(From domain)', $sender->selector, $status, $details];

        if (null === $sender->domain && null === $sender->privateKey) {
            return $row(self::SKIPPED, 'No dkim_domain: pass --domain=<domain> to check this sender.');
        }

        try {
            $publicKey = PrivateKey::load($sender)->publicKey();
        } catch (InvalidDkimConfiguration $e) {
            return $row(self::KEY_ERROR, $e->getMessage());
        }

        if ($this->option('no-dns')) {
            return $row(self::OK, 'Key is valid, DNS not checked (--no-dns).');
        }

        $dnsName = $sender->dnsName();

        if (null === $dnsName) {
            return $row(self::OK, 'Key is valid. No dkim_domain: pass --domain=<domain> to check the DNS record.');
        }

        return $row(...$this->checkDns($dns->txtRecords($dnsName), $dnsName, $publicKey));
    }

    /**
     * @param  list<string>  $records
     * @return array{string, string}
     */
    private function checkDns(array $records, string $dnsName, string $publicKey): array
    {
        if ([] === $records) {
            return [self::DNS_MISSING, sprintf('No TXT record found at %s.', $dnsName)];
        }

        $dkim = array_values(array_filter(
            array_map(DkimSigner::tags(...), $records),
            fn (array $tags) => isset($tags['p']) || 'DKIM1' === ($tags['v'] ?? null),
        ));

        if ([] === $dkim) {
            return [self::DNS_INVALID, sprintf('The TXT record at %s is no DKIM record.', $dnsName)];
        }

        if (count($dkim) > 1) {
            return [self::DNS_INVALID, sprintf('There is more than one DKIM record at %s.', $dnsName)];
        }

        $tags = $dkim[0];

        $problem = match (true) {
            isset($tags['v']) && 'DKIM1' !== $tags['v']           => 'The record must start with "v=DKIM1".',
            isset($tags['k']) && 'rsa' !== strtolower($tags['k']) => 'The key type (k=) must be "rsa".',
            '' === ($tags['p'] ?? '')                             => 'The public key (p=) is empty, the key is revoked.',
            false === base64_decode($tags['p'], true)             => 'The public key (p=) is not valid base64.',
            default                                               => null,
        };

        if (null !== $problem) {
            return [self::DNS_INVALID, $problem];
        }

        if ($tags['p'] !== $publicKey) {
            return [self::DNS_MISMATCH, sprintf('The public key at %s does not match the private key.', $dnsName)];
        }

        return [self::OK, sprintf('Key is valid and matches the DNS record at %s.', $dnsName)];
    }
}
