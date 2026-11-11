<?php

declare(strict_types=1);

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use TobyMaxham\LaravelDkimSigner\Tests\Fixtures\Keys;
use TobyMaxham\LaravelDkimSigner\Tests\Fixtures\DkimVerifier;

beforeEach(function () {
    config()->set('laravel-dkim.dkim_signer_enabled', true);
    $this->setSender('default', [
        'dkim_selector'    => 'mail',
        'dkim_domain'      => 'example.com',
        'dkim_private_key' => Keys::file(),
    ]);
});

function verifySent(string $raw, string $key = 'default', ?string $passphrase = null): array
{
    $result = DkimVerifier::verify($raw, Keys::pair($key, $passphrase)['public']);

    expect($result['tags'])->toMatchArray(['v' => '1', 'a' => 'rsa-sha256', 'd' => 'example.com', 's' => 'mail'])
        ->and($result['tags']['bh'])->toBe($result['bodyHash'])
        ->and($result['valid'])->toBeTrue('The signature b= does not verify with the public key.');

    return $result;
}

it('sends a raw mail with a valid signature', function () {
    Mail::raw("Hello  World \n\n\n", fn ($m) => $m->to('to@example.org')->from('info@example.com')->subject('Test  mail'));

    verifySent($this->lastSentRaw());
});

it('sends a multipart mailable with a valid signature', function () {
    Mail::to('to@example.org')->send(new class extends Mailable
    {
        public function build(): static
        {
            return $this->from('info@mail.example.com')
                ->subject('Multipart')
                ->html('<p>Hello <b>World</b></p>')
                ->attachData('file content', 'file.txt', ['mime' => 'text/plain']);
        }
    });

    $raw = $this->lastSentRaw();

    expect($raw)->toContain('multipart/mixed');
    verifySent($raw);
});

it('signs the headers that are added on send', function () {
    Mail::raw('Hello', fn ($m) => $m->to('to@example.org')->from('info@example.com'));

    $tags = verifySent($this->lastSentRaw())['tags'];

    expect(explode(':', strtolower($tags['h'])))->toContain('from', 'to', 'date', 'message-id', 'mime-version');
});

it('signs with simple canonicalization and an expiration', function () {
    $this->setSender('default', ['options' => ['header_canon' => 'simple', 'body_canon' => 'simple', 'signature_expiration_delay' => 3600]]);

    Mail::raw('Hello  World', fn ($m) => $m->to('to@example.org')->from('info@example.com'));

    $tags = verifySent($this->lastSentRaw())['tags'];

    expect($tags['c'])->toBe('simple/simple')
        ->and((int) $tags['x'] - (int) $tags['t'])->toBe(3600);
});

it('signs with an encrypted key', function () {
    $this->setSender('default', ['dkim_private_key' => Keys::file('enc', 'secret'), 'dkim_passphrase' => 'secret']);

    Mail::raw('Hello', fn ($m) => $m->to('to@example.org')->from('info@example.com'));

    verifySent($this->lastSentRaw(), 'enc', 'secret');
});

it('signs every mail with the sender of its domain', function () {
    $this->setSender('org', ['dkim_selector' => 'org', 'dkim_domain' => 'example.org', 'dkim_private_key' => Keys::file('org')]);

    Mail::raw('A', fn ($m) => $m->to('to@example.net')->from('info@example.com'));
    Mail::raw('B', fn ($m) => $m->to('to@example.net')->from('info@example.org'));

    [$a, $b] = $this->sentMessages()->map->toString()->all();

    expect(DkimVerifier::verify($a, Keys::pair()['public']))->toMatchArray(['valid' => true])
        ->and(DkimVerifier::verify($b, Keys::pair('org')['public']))->toMatchArray(['valid' => true])
        ->and(DkimVerifier::verify($b, Keys::pair('org')['public'])['tags'])->toMatchArray(['d' => 'example.org', 's' => 'org']);
});

it('detects a changed mail (the verifier is not always true)', function () {
    Mail::raw('Hello', fn ($m) => $m->to('to@example.org')->from('info@example.com')->subject('Original'));
    $raw = $this->lastSentRaw();

    $changedHeader = DkimVerifier::verify(str_replace('Subject: Original', 'Subject: Changed', $raw), Keys::pair()['public']);
    $changedBody = DkimVerifier::verify(str_replace("\r\n\r\nHello", "\r\n\r\nHello!", $raw), Keys::pair()['public']);
    $otherKey = DkimVerifier::verify($raw, Keys::pair('other')['public']);

    expect($changedHeader['valid'])->toBeFalse()
        ->and($changedBody['bodyHash'])->not->toBe($changedBody['tags']['bh'])
        ->and($otherKey['valid'])->toBeFalse();
});
