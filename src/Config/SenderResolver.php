<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelDkimSigner\Config;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Message;
use Illuminate\Contracts\Config\Repository;
use TobyMaxham\LaravelDkimSigner\Exceptions\NoMatchingSender;
use TobyMaxham\LaravelDkimSigner\Exceptions\InvalidDkimConfiguration;

/**
 * Picks the sender for a message by its From address:
 * 1. exact address (addresses), 2. domain (dkim_domain, longest match), 3. sender without dkim_domain, 4. dkim_fallback.
 */
final readonly class SenderResolver
{
    public const array FALLBACKS = ['skip', 'default', 'fail'];

    public function __construct(private Repository $config) {}

    /**
     * The sender for the message, with a domain, or null if the message should not be signed.
     */
    public function resolve(Message $message): ?SenderConfig
    {
        $address = $this->fromAddress($message);
        $domain = null === $address ? null : substr($address, strrpos($address, '@') + 1);
        $senders = $this->senders();

        $wildcard = array_values(array_filter($senders, fn (SenderConfig $sender) => null === $sender->domain))[0] ?? null;

        if (null !== $address && null !== $domain) {
            $sender = $this->byAddress($senders, $address)
                ?? $this->byDomain($senders, $domain)
                ?? $wildcard;

            if (null !== $sender) {
                return null !== $sender->domain ? $sender : $sender->withDomain($domain);
            }
        }

        return $this->fallback($senders, $address, $domain);
    }

    /**
     * All configured senders, validated. At most one sender may have no dkim_domain.
     *
     * @return array<string, SenderConfig>
     */
    public function senders(): array
    {
        $config = $this->config->get('laravel-dkim.sender', []);

        if (! is_array($config)) {
            throw InvalidDkimConfiguration::forKey('laravel-dkim.sender', 'The value must be an array of senders.');
        }

        $senders = [];

        foreach ($config as $name => $sender) {
            if (! is_array($sender)) {
                throw InvalidDkimConfiguration::forKey('laravel-dkim.sender.'.$name, 'The value must be an array.');
            }

            $senders[(string) $name] = SenderConfig::fromArray((string) $name, $sender);
        }

        $wildcards = array_keys(array_filter($senders, fn (SenderConfig $sender) => null === $sender->domain));
        if (count($wildcards) > 1) {
            throw InvalidDkimConfiguration::forKey('laravel-dkim.sender', 'Only one sender may have an empty dkim_domain, found: '.implode(', ', $wildcards).'.');
        }

        return $senders;
    }

    public function fallbackMode(): string
    {
        $mode = $this->config->get('laravel-dkim.dkim_fallback') ?? 'skip';

        if (! in_array($mode, self::FALLBACKS, true)) {
            throw InvalidDkimConfiguration::forKey('laravel-dkim.dkim_fallback', 'The value must be one of: '.implode(', ', self::FALLBACKS).'.');
        }

        return $mode;
    }

    /**
     * @param  array<string, SenderConfig>  $senders
     */
    private function fallback(array $senders, ?string $address, ?string $domain): ?SenderConfig
    {
        $mode = $this->fallbackMode();

        if ('fail' === $mode) {
            throw NoMatchingSender::forAddress($address);
        }

        if ('skip' === $mode) {
            return null;
        }

        $name = (string) $this->config->get('laravel-dkim.dkim_default_sender', 'default');
        $sender = $senders[$name] ?? throw InvalidDkimConfiguration::forKey(
            'laravel-dkim.dkim_default_sender',
            sprintf('The sender "%s" does not exist in laravel-dkim.sender.', $name),
        );

        if (null !== $sender->domain) {
            return $sender;
        }

        return null === $domain ? null : $sender->withDomain($domain);
    }

    /**
     * @param  array<string, SenderConfig>  $senders
     */
    private function byAddress(array $senders, string $address): ?SenderConfig
    {
        foreach ($senders as $sender) {
            if (in_array($address, $sender->addresses, true)) {
                return $sender;
            }
        }

        return null;
    }

    /**
     * The sender whose dkim_domain equals the domain or is a parent of it. The longest domain wins.
     *
     * @param  array<string, SenderConfig>  $senders
     */
    private function byDomain(array $senders, string $domain): ?SenderConfig
    {
        $match = null;

        foreach ($senders as $sender) {
            if (null === $sender->domain || ($domain !== $sender->domain && ! str_ends_with($domain, '.'.$sender->domain))) {
                continue;
            }

            if (null === $match || strlen((string) $sender->domain) > strlen((string) $match->domain)) {
                $match = $sender;
            }
        }

        return $match;
    }

    private function fromAddress(Message $message): ?string
    {
        $from = $message->getHeaders()->get('From');
        $addresses = null === $from ? [] : $from->getBody();

        if (! is_array($addresses) || ! ($addresses[0] ?? null) instanceof Address) {
            return null;
        }

        return strtolower($addresses[0]->getAddress());
    }
}
