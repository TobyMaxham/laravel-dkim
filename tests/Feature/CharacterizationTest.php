<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Event;
use Illuminate\Mail\Events\MessageSending;
use TobyMaxham\LaravelDkimSigner\Tests\Fixtures\Keys;
use TobyMaxham\LaravelDkimSigner\Listener\ApplyDkimSignature;
use TobyMaxham\LaravelDkimSigner\Exceptions\InvalidDkimConfiguration;

/*
 * Behavior of 3.x, recorded before the refactoring to 4.0.
 * The tests prefixed with "4.0:" changed on purpose, see UPGRADING.md.
 */

beforeEach(function () {
    config()->set('laravel-dkim.dkim_signer_enabled', true);
    $this->setSender('default', [
        'dkim_selector'    => 'mail',
        'dkim_domain'      => 'a.example',
        'dkim_private_key' => Keys::file(),
    ]);
});

it('registers the listener for MessageSending', function () {
    expect(Event::hasListeners(MessageSending::class))->toBeTrue()
        ->and(Event::getRawListeners()[MessageSending::class])->toContain(ApplyDkimSignature::class);
});

it('does not sign when disabled', function () {
    config()->set('laravel-dkim.dkim_signer_enabled', false);

    Mail::raw('Hello', fn ($m) => $m->to('to@a.example')->from('x@a.example'));

    expect($this->lastSentRaw())->not->toContain('DKIM-Signature');
});

it('signs with the configured domain, selector and algorithm', function () {
    Mail::raw('Hello', fn ($m) => $m->to('to@a.example')->from('x@a.example'));

    expect($this->lastSentRaw())->toContain('DKIM-Signature:')
        ->toContain('d=a.example;')
        ->toContain('s=mail;')
        ->toContain('a=rsa-sha256;');
});

it('4.0: does not match the sender name against the From address any more', function () {
    // 3.x picked this sender for info@example.com, because of its name
    $this->setSender('info@example_com', [
        'dkim_selector'    => 'other',
        'dkim_domain'      => 'other.example',
        'dkim_private_key' => Keys::file('other'),
    ]);

    Mail::raw('Hello', fn ($m) => $m->to('to@a.example')->from('info@example.com'));

    expect($this->lastSentRaw())->not->toContain('DKIM-Signature');
});

it('4.0: does not sign a mail of another domain with the default sender', function () {
    // 3.x signed it with d=a.example, which breaks DMARC alignment
    Mail::raw('Hello', fn ($m) => $m->to('to@b.example')->from('x@b.example'));

    expect($this->lastSentRaw())->not->toContain('DKIM-Signature');
});

it('4.0: keeps the 3.x fallback with dkim_fallback=default', function () {
    config()->set('laravel-dkim.dkim_fallback', 'default');

    Mail::raw('Hello', fn ($m) => $m->to('to@b.example')->from('x@b.example'));

    expect($this->lastSentRaw())->toContain('d=a.example;');
});

it('4.0: names the config key when the key is missing', function () {
    $this->setSender('default', ['dkim_private_key' => '/does/not/exist.pem']);

    Mail::raw('Hello', fn ($m) => $m->to('to@a.example')->from('x@a.example'));
})->throws(InvalidDkimConfiguration::class, 'Invalid DKIM configuration [laravel-dkim.sender.default.dkim_private_key]');

it('4.0: keeps Bcc recipients when signing', function () {
    // 3.x replaced all headers with the signed copy, which has no Bcc header
    Mail::raw('Hello', fn ($m) => $m->to('to@a.example')->bcc('hidden@a.example')->from('x@a.example'));

    $recipients = array_map(fn ($address) => $address->getAddress(), $this->sentMessages()->last()->getEnvelope()->getRecipients());

    expect($recipients)->toBe(['to@a.example', 'hidden@a.example'])
        ->and($this->lastSentRaw())->toContain('DKIM-Signature')->not->toContain('hidden@a.example');
});
