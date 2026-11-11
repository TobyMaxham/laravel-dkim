<?php

declare(strict_types=1);

use TobyMaxham\LaravelDkimSigner\Dns\NativeDnsResolver;

it('joins the strings of a TXT record', function () {
    $resolver = new NativeDnsResolver(fn (string $host) => [
        ['host' => $host, 'type' => 'TXT', 'txt' => 'v=DKIM1; p=abcdef', 'entries' => ['v=DKIM1; p=abc', 'def']],
        ['host' => $host, 'type' => 'TXT', 'txt' => 'only-txt'],
        ['host' => $host, 'type' => 'CNAME', 'target' => 'x.example.com'],
        'garbage',
    ]);

    expect($resolver->txtRecords('mail._domainkey.example.com'))->toBe(['v=DKIM1; p=abcdef', 'only-txt']);
});

it('returns no records when the lookup fails', function () {
    expect((new NativeDnsResolver(fn () => false))->txtRecords('x.example.com'))->toBe([]);
});

it('uses dns_get_record by default', function () {
    // .invalid never exists (RFC 6761), so this does not depend on real DNS data
    expect((new NativeDnsResolver)->txtRecords('dkim-test.invalid'))->toBe([]);
});
