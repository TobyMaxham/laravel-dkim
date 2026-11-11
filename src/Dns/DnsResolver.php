<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelDkimSigner\Dns;

interface DnsResolver
{
    /**
     * The TXT records of a hostname. Each record is returned as one string,
     * records that are split into several strings are joined.
     *
     * @return list<string>
     */
    public function txtRecords(string $hostname): array;
}
