<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelDkimSigner\Exceptions;

/**
 * No sender matches the From address and the fallback is "fail".
 */
class NoMatchingSender extends DkimException
{
    public static function forAddress(?string $address): self
    {
        return new self(null === $address
            ? 'No DKIM sender matches the message, because it has no From address.'
            : sprintf('No DKIM sender matches the From address "%s".', $address));
    }
}
