<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelDkimSigner\Config;

use TobyMaxham\LaravelDkimSigner\Exceptions\InvalidDkimConfiguration;

/**
 * The validated config of one sender in "laravel-dkim.sender".
 */
final readonly class SenderConfig
{
    /** Algorithms that symfony/mime can sign with. It also knows "ed25519-sha256", but throws "not supported yet". */
    public const array ALGORITHMS = ['rsa-sha256'];

    public const array CANONICALIZATIONS = ['relaxed', 'simple'];

    /**
     * @param  list<string>  $addresses
     * @param  array{header_canon?: string, body_canon?: string, signature_expiration_delay?: int, headers_to_ignore?: list<string>}  $options
     */
    public function __construct(
        public string $name,
        public string $selector,
        public ?string $domain,
        public ?string $privateKey,
        public ?string $passphrase,
        public string $algorithm = 'rsa-sha256',
        public array $addresses = [],
        public array $options = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function fromArray(string $name, array $config): self
    {
        $key = fn (string $option): string => 'laravel-dkim.sender.'.$name.'.'.$option;

        $selector = self::string($config['dkim_selector'] ?? null, $key('dkim_selector'));
        if (null === $selector || ! self::isValidSelector($selector)) {
            throw InvalidDkimConfiguration::forKey($key('dkim_selector'), 'The selector must be a valid DNS name like "default" or "mail-2025".');
        }

        $domain = self::string($config['dkim_domain'] ?? null, $key('dkim_domain'));
        if (null !== $domain && ! self::isValidDomain($domain)) {
            throw InvalidDkimConfiguration::forKey($key('dkim_domain'), sprintf('"%s" is not a valid domain name.', $domain));
        }

        $algorithm = self::string($config['dkim_algo'] ?? null, $key('dkim_algo')) ?? 'rsa-sha256';
        if (! in_array($algorithm, self::ALGORITHMS, true)) {
            throw InvalidDkimConfiguration::forKey($key('dkim_algo'), sprintf('The algorithm must be one of: %s.', implode(', ', self::ALGORITHMS)));
        }

        return new self(
            name: $name,
            selector: strtolower($selector),
            domain: null === $domain ? null : strtolower($domain),
            privateKey: self::string($config['dkim_private_key'] ?? null, $key('dkim_private_key'), trim: false),
            passphrase: self::string($config['dkim_passphrase'] ?? null, $key('dkim_passphrase'), trim: false),
            algorithm: $algorithm,
            addresses: self::addresses($config['addresses'] ?? [], $key('addresses')),
            options: self::options($config['options'] ?? [], $key('options')),
        );
    }

    /**
     * The same sender for a concrete domain (used for senders without dkim_domain).
     */
    public function withDomain(string $domain): self
    {
        return new self($this->name, $this->selector, strtolower($domain), $this->privateKey, $this->passphrase, $this->algorithm, $this->addresses, $this->options);
    }

    /**
     * The config key of an option of this sender, e.g. "laravel-dkim.sender.default.dkim_selector".
     */
    public function configKey(string $option): string
    {
        return 'laravel-dkim.sender.'.$this->name.'.'.$option;
    }

    /**
     * The name of the DNS TXT record, e.g. "default._domainkey.example.com".
     */
    public function dnsName(): ?string
    {
        return null === $this->domain ? null : $this->selector.'._domainkey.'.$this->domain;
    }

    /**
     * Where the key of a domain and selector is stored when dkim_private_key is not set.
     */
    public static function defaultKeyPath(string $domain, string $selector): string
    {
        return storage_path('app/dkim/'.strtolower($domain).'.'.strtolower($selector).'.pem');
    }

    public static function isValidSelector(string $selector): bool
    {
        return self::isDnsName($selector, 1);
    }

    public static function isValidDomain(string $domain): bool
    {
        return self::isDnsName($domain, 2);
    }

    private static function isDnsName(string $name, int $minLabels): bool
    {
        if (strlen($name) > 253) {
            return false;
        }

        $labels = explode('.', $name);

        if (count($labels) < $minLabels) {
            return false;
        }

        foreach ($labels as $label) {
            if (1 !== preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i', $label)) {
                return false;
            }
        }

        return true;
    }

    private static function string(mixed $value, string $configKey, bool $trim = true): ?string
    {
        if (null === $value) {
            return null;
        }

        if (! is_string($value)) {
            throw InvalidDkimConfiguration::forKey($configKey, 'The value must be a string.');
        }

        $value = $trim ? trim($value) : $value;

        return '' === trim($value) ? null : $value;
    }

    /**
     * @return list<string>
     */
    private static function addresses(mixed $value, string $configKey): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            throw InvalidDkimConfiguration::forKey($configKey, 'The value must be a list of email addresses.');
        }

        $addresses = [];

        foreach ($value as $address) {
            $address = is_string($address) ? strtolower(trim($address)) : $address;

            if ('' === $address) {
                continue;
            }

            if (! is_string($address) || false === filter_var($address, FILTER_VALIDATE_EMAIL)) {
                throw InvalidDkimConfiguration::forKey($configKey, 'The value must be a list of email addresses.');
            }

            $addresses[] = $address;
        }

        return array_values(array_unique($addresses));
    }

    /**
     * @return array{header_canon?: string, body_canon?: string, signature_expiration_delay?: int, headers_to_ignore?: list<string>}
     */
    private static function options(mixed $value, string $configKey): array
    {
        if (! is_array($value)) {
            throw InvalidDkimConfiguration::forKey($configKey, 'The value must be an array.');
        }

        $unknown = array_diff(array_keys($value), ['header_canon', 'body_canon', 'signature_expiration_delay', 'headers_to_ignore']);
        if ([] !== $unknown) {
            throw InvalidDkimConfiguration::forKey($configKey.'.'.reset($unknown), 'Unknown option. Allowed: header_canon, body_canon, signature_expiration_delay, headers_to_ignore.');
        }

        $options = [];

        foreach (['header_canon', 'body_canon'] as $option) {
            if (array_key_exists($option, $value)) {
                $options[$option] = in_array($value[$option], self::CANONICALIZATIONS, true)
                    ? $value[$option]
                    : throw InvalidDkimConfiguration::forKey($configKey.'.'.$option, 'The value must be "relaxed" or "simple".');
            }
        }

        if (array_key_exists('signature_expiration_delay', $value)) {
            $delay = $value['signature_expiration_delay'];
            $options['signature_expiration_delay'] = is_int($delay) && $delay >= 0
                ? $delay
                : throw InvalidDkimConfiguration::forKey($configKey.'.signature_expiration_delay', 'The value must be a number of seconds (0 = no expiration).');
        }

        if (array_key_exists('headers_to_ignore', $value)) {
            $headers = $value['headers_to_ignore'];
            $options['headers_to_ignore'] = is_array($headers) && array_is_list($headers) && [] === array_filter($headers, fn (mixed $header) => ! is_string($header))
                ? $headers
                : throw InvalidDkimConfiguration::forKey($configKey.'.headers_to_ignore', 'The value must be a list of header names.');
        }

        return $options;
    }
}
