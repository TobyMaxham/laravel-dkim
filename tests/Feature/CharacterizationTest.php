<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Event;
use Illuminate\Mail\Events\MessageSending;
use TobyMaxham\LaravelDkimSigner\Tests\Fixtures\Keys;
use TobyMaxham\LaravelDkimSigner\Listener\ApplyDkimSignature;

/*
 * Behavior of 3.x, recorded before the refactoring to 4.0.
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

it('3.x: picks the sender by the full From address with dots replaced', function () {
    $this->setSender('info@example_com', [
        'dkim_selector'    => 'other',
        'dkim_domain'      => 'example.com',
        'dkim_private_key' => Keys::file('other'),
    ]);

    Mail::raw('Hello', fn ($m) => $m->to('to@a.example')->from('info@example.com'));

    expect($this->lastSentRaw())->toContain('d=example.com;')->toContain('s=other;');
});

it('3.x: silently signs a mail of another domain with the default sender', function () {
    Mail::raw('Hello', fn ($m) => $m->to('to@b.example')->from('x@b.example'));

    expect($this->lastSentRaw())->toContain('d=a.example;');
});

it('3.x: a missing key blocks every mail', function () {
    $this->setSender('default', ['dkim_private_key' => null]);

    Mail::raw('Hello', fn ($m) => $m->to('to@a.example')->from('x@a.example'));
})->throws(RuntimeException::class, 'Some configuration is missing to add DKIM signature!');
