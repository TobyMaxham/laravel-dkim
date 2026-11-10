<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelDkimSigner;

use Throwable;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Message;
use Illuminate\Contracts\Config\Repository;
use TobyMaxham\LaravelDkimSigner\Key\PrivateKey;
use TobyMaxham\LaravelDkimSigner\Config\SenderConfig;
use TobyMaxham\LaravelDkimSigner\Config\SenderResolver;
use TobyMaxham\LaravelDkimSigner\Exceptions\DkimException;
use Symfony\Component\Mime\Crypto\DkimSigner as SymfonyDkimSigner;
use TobyMaxham\LaravelDkimSigner\Exceptions\InvalidDkimConfiguration;
use Symfony\Component\Mime\Exception\ExceptionInterface as MimeException;

/**
 * Signs messages with the sender that matches their From address.
 */
final readonly class DkimSigner
{
    public const array ON_ERROR = ['throw', 'log'];

    public function __construct(
        private Repository $config,
        private SenderResolver $resolver,
    ) {}

    public function isEnabled(): bool
    {
        return filter_var($this->config->get('laravel-dkim.dkim_signer_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * The signed message, or null if the message is not signed
     * (disabled, no matching sender, already signed or an error with dkim_on_error=log).
     */
    public function sign(Message $message): ?Message
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $onError = $this->errorMode();
        $sender = null;

        try {
            $sender = $this->resolver->resolve($message);

            if (null === $sender || $this->isSignedBy($message, $sender)) {
                return null;
            }

            $key = PrivateKey::load($sender);

            return (new SymfonyDkimSigner($key->pem, (string) $sender->domain, $sender->selector, [], $sender->passphrase ?? ''))
                ->sign($message, ['algorithm' => $sender->algorithm] + $sender->options);
        } catch (DkimException|MimeException $e) {
            if ('throw' === $onError) {
                throw $e;
            }

            $this->report($e, $sender);

            return null;
        }
    }

    /**
     * Whether the message has a DKIM-Signature with the domain and selector of the sender.
     */
    public function isSignedBy(Message $message, SenderConfig $sender): bool
    {
        foreach ($message->getHeaders()->all('DKIM-Signature') as $header) {
            $tags = self::tags($header->getBodyAsString());

            if (strtolower($tags['d'] ?? '') === $sender->domain && strtolower($tags['s'] ?? '') === $sender->selector) {
                return true;
            }
        }

        return false;
    }

    /**
     * The tags of a DKIM-Signature or DKIM DNS record, e.g. ['v' => 'DKIM1', 'p' => '...'].
     *
     * @return array<string, string>
     */
    public static function tags(string $value): array
    {
        $tags = [];

        foreach (explode(';', $value) as $part) {
            if (! str_contains($part, '=')) {
                continue;
            }

            [$name, $tagValue] = explode('=', $part, 2);
            $tags[strtolower(trim($name))] = (string) preg_replace('/\s+/', '', $tagValue);
        }

        return $tags;
    }

    public function errorMode(): string
    {
        $mode = $this->config->get('laravel-dkim.dkim_on_error') ?? 'throw';

        if (! in_array($mode, self::ON_ERROR, true)) {
            throw InvalidDkimConfiguration::forKey('laravel-dkim.dkim_on_error', 'The value must be one of: '.implode(', ', self::ON_ERROR).'.');
        }

        return $mode;
    }

    private function report(Throwable $e, ?SenderConfig $sender): void
    {
        Log::warning('The mail was sent without DKIM signature: '.$e->getMessage(), [
            'sender'    => $sender?->name,
            'exception' => $e::class,
        ]);
    }
}
