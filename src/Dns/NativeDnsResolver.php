<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelDkimSigner\Dns;

use Closure;

/**
 * Looks up TXT records with dns_get_record().
 */
final readonly class NativeDnsResolver implements DnsResolver
{
    /** @var Closure(string): mixed */
    private Closure $lookup;

    /**
     * @param  (Closure(string): mixed)|null  $lookup  replaces dns_get_record() in tests
     */
    public function __construct(?Closure $lookup = null)
    {
        $this->lookup = $lookup ?? fn (string $hostname): mixed => @dns_get_record($hostname, DNS_TXT);
    }

    public function txtRecords(string $hostname): array
    {
        $records = ($this->lookup)($hostname);

        if (! is_array($records)) {
            return [];
        }

        $txt = [];

        foreach ($records as $record) {
            if (! is_array($record) || 'TXT' !== ($record['type'] ?? null)) {
                continue;
            }

            $txt[] = is_array($record['entries'] ?? null)
                ? implode('', array_map('strval', $record['entries']))
                : (string) ($record['txt'] ?? '');
        }

        return $txt;
    }
}
