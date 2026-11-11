<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelDkimSigner\Tests\Fixtures;

use TobyMaxham\LaravelDkimSigner\Dns\DnsResolver;

final class FakeDnsResolver implements DnsResolver
{
    /** @var list<string> */
    public array $lookups = [];

    /**
     * @param  array<string, list<string>>  $records
     */
    public function __construct(private array $records = []) {}

    public function txtRecords(string $hostname): array
    {
        $this->lookups[] = $hostname;

        return $this->records[$hostname] ?? [];
    }
}
