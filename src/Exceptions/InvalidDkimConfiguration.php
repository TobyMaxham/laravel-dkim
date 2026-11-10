<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelDkimSigner\Exceptions;

/**
 * The config is invalid or the private key can not be used.
 * The message names the config key, never the key or the passphrase.
 */
class InvalidDkimConfiguration extends DkimException
{
    public function __construct(string $message, public readonly string $configKey)
    {
        parent::__construct($message);
    }

    public static function forKey(string $configKey, string $problem): self
    {
        return new self(sprintf('Invalid DKIM configuration [%s]: %s', $configKey, $problem), $configKey);
    }
}
