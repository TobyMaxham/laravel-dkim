<?php

declare(strict_types=1);

use Symfony\Component\Mime\Email;
use TobyMaxham\LaravelDkimSigner\Config\SenderResolver;
use TobyMaxham\LaravelDkimSigner\Exceptions\NoMatchingSender;
use TobyMaxham\LaravelDkimSigner\Exceptions\InvalidDkimConfiguration;

function resolveFor(?string $from, array $senders, array $config = []): ?array
{
    config()->set('laravel-dkim.sender', $senders);

    foreach ($config as $key => $value) {
        config()->set('laravel-dkim.'.$key, $value);
    }

    $message = new Email;
    if (null !== $from) {
        $message->from($from);
    }

    $sender = app(SenderResolver::class)->resolve($message);

    return null === $sender ? null : [$sender->name, $sender->domain];
}

function sender(?string $domain, array $addresses = []): array
{
    return ['dkim_selector' => 'default', 'dkim_domain' => $domain, 'addresses' => $addresses];
}

it('resolves the sender', function (?string $from, array $senders, ?array $expected, array $config = []) {
    expect(resolveFor($from, $senders, $config))->toBe($expected);
})->with([
    'exact address wins over domain'    => ['ceo@example.com', ['a' => sender('example.com'), 'b' => sender('example.com', ['ceo@example.com'])], ['b', 'example.com']],
    'exact address is case insensitive' => ['CEO@Example.COM', ['a' => sender('example.com'), 'b' => sender('example.com', ['Ceo@example.com'])], ['b', 'example.com']],
    'exact address of a wildcard'       => ['ceo@example.org', ['w' => sender(null, ['ceo@example.org'])], ['w', 'example.org']],
    'same domain'                       => ['info@example.com', ['a' => sender('example.com')], ['a', 'example.com']],
    'domain is case insensitive'        => ['info@EXAMPLE.com', ['a' => sender('Example.Com')], ['a', 'example.com']],
    'subdomain (relaxed alignment)'     => ['info@mail.example.com', ['a' => sender('example.com')], ['a', 'example.com']],
    'no suffix match without dot'       => ['info@notexample.com', ['a' => sender('example.com')], null],
    'parent does not match child'       => ['info@example.com', ['a' => sender('mail.example.com')], null],
    'longest domain wins'               => ['info@a.mail.example.com', ['short' => sender('example.com'), 'long' => sender('mail.example.com')], ['long', 'mail.example.com']],
    'longest domain wins in any order'  => ['info@a.mail.example.com', ['long' => sender('mail.example.com'), 'short' => sender('example.com')], ['long', 'mail.example.com']],
    'first sender wins on equal domain' => ['info@example.com', ['first' => sender('example.com'), 'second' => sender('example.com')], ['first', 'example.com']],
    'wildcard signs the From domain'    => ['info@foo.test', ['w' => sender(null)], ['w', 'foo.test']],
    'domain wins over wildcard'         => ['info@example.com', ['w' => sender(null), 'a' => sender('example.com')], ['a', 'example.com']],
    'skip without match'                => ['info@other.test', ['a' => sender('example.com')], null, ['dkim_fallback' => 'skip']],
    'skip is the default'               => ['info@other.test', ['a' => sender('example.com')], null, ['dkim_fallback' => null]],
    'default fallback'                  => ['info@other.test', ['default' => sender('example.com')], ['default', 'example.com'], ['dkim_fallback' => 'default']],
    'default fallback with custom name' => ['info@other.test', ['a' => sender('example.com'), 'b' => sender('example.org')], ['b', 'example.org'], ['dkim_fallback' => 'default', 'dkim_default_sender' => 'b']],
    'no From, skip'                     => [null, ['w' => sender(null)], null],
    'no From, default with domain'      => [null, ['default' => sender('example.com')], ['default', 'example.com'], ['dkim_fallback' => 'default']],
    'no From, default without domain'   => [null, ['default' => sender(null)], null, ['dkim_fallback' => 'default']],
    'no senders'                        => ['info@example.com', [], null],
]);

it('throws with fallback fail', function () {
    resolveFor('info@other.test', ['a' => sender('example.com')], ['dkim_fallback' => 'fail']);
})->throws(NoMatchingSender::class, 'No DKIM sender matches the From address "info@other.test".');

it('throws with fallback fail and no From', function () {
    resolveFor(null, ['a' => sender('example.com')], ['dkim_fallback' => 'fail']);
})->throws(NoMatchingSender::class, 'because it has no From address');

it('rejects two senders without domain', function () {
    resolveFor('info@example.com', ['w1' => sender(null), 'w2' => sender(null), 'a' => sender('example.com')]);
})->throws(InvalidDkimConfiguration::class, 'Only one sender may have an empty dkim_domain, found: w1, w2.');

it('rejects an unknown fallback', function () {
    resolveFor('info@other.test', ['a' => sender('example.com')], ['dkim_fallback' => 'sometimes']);
})->throws(InvalidDkimConfiguration::class, '[laravel-dkim.dkim_fallback]');

it('rejects a missing default sender', function () {
    resolveFor('info@other.test', ['a' => sender('example.com')], ['dkim_fallback' => 'default', 'dkim_default_sender' => 'missing']);
})->throws(InvalidDkimConfiguration::class, 'The sender "missing" does not exist');

it('rejects senders that are not an array', function () {
    config()->set('laravel-dkim.sender', 'nope');

    app(SenderResolver::class)->senders();
})->throws(InvalidDkimConfiguration::class, '[laravel-dkim.sender]');

it('rejects a sender that is not an array', function () {
    config()->set('laravel-dkim.sender', ['a' => 'nope']);

    app(SenderResolver::class)->senders();
})->throws(InvalidDkimConfiguration::class, '[laravel-dkim.sender.a]');
