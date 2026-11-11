<?php

declare(strict_types=1);

use Symfony\Component\Mime\Email;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Event;
use Illuminate\Mail\Events\MessageSending;
use TobyMaxham\LaravelDkimSigner\DkimSigner;
use TobyMaxham\LaravelDkimSigner\Tests\Fixtures\Keys;
use TobyMaxham\LaravelDkimSigner\Exceptions\NoMatchingSender;
use TobyMaxham\LaravelDkimSigner\Exceptions\InvalidDkimConfiguration;
use Symfony\Component\Mime\Exception\LogicException as MimeLogicException;

beforeEach(function () {
    config()->set('laravel-dkim.dkim_signer_enabled', true);
    $this->setSender('default', [
        'dkim_selector'    => 'mail',
        'dkim_domain'      => 'example.com',
        'dkim_private_key' => Keys::file(),
    ]);
});

function signatures(string $raw): int
{
    return substr_count($raw, "\r\nDKIM-Signature:");
}

it('reads the enabled flag from env strings', function (mixed $value, bool $enabled) {
    config()->set('laravel-dkim.dkim_signer_enabled', $value);

    expect(app(DkimSigner::class)->isEnabled())->toBe($enabled);
})->with([
    [true, true], ['true', true], ['1', true], [false, false], ['false', false], ['', false], [null, false],
]);

it('returns null when disabled', function () {
    config()->set('laravel-dkim.dkim_signer_enabled', false);

    expect(app(DkimSigner::class)->sign((new Email)->from('info@example.com')->text('x')))->toBeNull();
});

it('does not sign a message without From', function () {
    expect(app(DkimSigner::class)->sign((new Email)->text('x')))->toBeNull();
});

it('does not sign twice with the same domain and selector', function () {
    $message = (new Email)->from('info@example.com')->to('to@example.org')->text('x');

    event(new MessageSending($message));
    event(new MessageSending($message));

    expect(iterator_to_array($message->getHeaders()->all('DKIM-Signature'), false))->toHaveCount(1);
});

it('adds a signature next to the signature of another selector', function () {
    $this->setSender('second', ['dkim_selector' => 'other', 'dkim_domain' => 'example.com', 'dkim_private_key' => Keys::file('other'), 'addresses' => ['info@example.com']]);
    $message = (new Email)->from('info@example.com')->to('to@example.org')->text('x');

    event(new MessageSending($message));
    config()->set('laravel-dkim.sender.second.addresses', []);
    event(new MessageSending($message));

    $selectors = array_map(fn ($header) => DkimSigner::tags($header->getBodyAsString())['s'], iterator_to_array($message->getHeaders()->all('DKIM-Signature'), false));

    expect($selectors)->toBe(['other', 'mail']);
});

it('sends one signature per mail', function () {
    Mail::raw('Hello', fn ($m) => $m->to('to@example.org')->from('info@example.com'));

    expect(signatures($this->lastSentRaw()))->toBe(1);
});

it('throws by default when the mail can not be signed', function () {
    $this->setSender('default', ['dkim_private_key' => '/missing.pem']);

    Mail::raw('Hello', fn ($m) => $m->to('to@example.org')->from('info@example.com'));
})->throws(InvalidDkimConfiguration::class, '[laravel-dkim.sender.default.dkim_private_key]');

it('logs and sends unsigned with dkim_on_error=log', function () {
    config()->set('laravel-dkim.dkim_on_error', 'log');
    $this->setSender('default', ['dkim_private_key' => Keys::file('enc', 'secret'), 'dkim_passphrase' => 'wrong-secret']);
    Log::spy();

    Mail::raw('Hello', fn ($m) => $m->to('to@example.org')->from('info@example.com'));

    expect($this->lastSentRaw())->not->toContain('DKIM-Signature');
    Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) {
        return str_contains($message, 'The mail was sent without DKIM signature: Invalid DKIM configuration [laravel-dkim.sender.default.dkim_passphrase]')
            && ! str_contains($message.json_encode($context), 'secret')
            && $context === ['sender' => 'default', 'exception' => InvalidDkimConfiguration::class];
    });
});

it('logs a missing sender with dkim_fallback=fail and dkim_on_error=log', function () {
    config()->set('laravel-dkim.dkim_on_error', 'log');
    config()->set('laravel-dkim.dkim_fallback', 'fail');
    Log::spy();

    Mail::raw('Hello', fn ($m) => $m->to('to@example.org')->from('info@other.test'));

    expect($this->sentMessages())->toHaveCount(1);
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => $context === ['sender' => null, 'exception' => NoMatchingSender::class]);
});

it('blocks the mail with dkim_fallback=fail', function () {
    config()->set('laravel-dkim.dkim_fallback', 'fail');

    Mail::raw('Hello', fn ($m) => $m->to('to@example.org')->from('info@other.test'));
})->throws(NoMatchingSender::class);

it('handles errors of symfony/mime like its own', function (string $mode) {
    config()->set('laravel-dkim.dkim_on_error', $mode);
    config()->set('laravel-dkim.dkim_fallback', 'default');
    Log::spy();

    // the default sender has a domain, but Symfony can not sign a message without From
    $result = app(DkimSigner::class)->sign((new Email)->text('x'));

    expect($result)->toBeNull();
    Log::shouldHaveReceived('warning')->once();
})->with(['log']);

it('throws errors of symfony/mime with dkim_on_error=throw', function () {
    config()->set('laravel-dkim.dkim_fallback', 'default');

    app(DkimSigner::class)->sign((new Email)->text('x'));
})->throws(MimeLogicException::class);

it('rejects an unknown dkim_on_error', function () {
    config()->set('laravel-dkim.dkim_on_error', 'ignore');

    app(DkimSigner::class)->sign((new Email)->from('info@example.com')->text('x'));
})->throws(InvalidDkimConfiguration::class, '[laravel-dkim.dkim_on_error]');

it('uses throw when dkim_on_error is null', function () {
    config()->set('laravel-dkim.dkim_on_error', null);

    expect(app(DkimSigner::class)->errorMode())->toBe('throw');
});

it('parses DKIM tags', function () {
    expect(DkimSigner::tags("v=1; a=rsa-sha256; d=Example.com;\r\n s=mail; h=From: To; b=ab\r\n cd; broken"))
        ->toBe(['v' => '1', 'a' => 'rsa-sha256', 'd' => 'Example.com', 's' => 'mail', 'h' => 'From:To', 'b' => 'abcd']);
});

it('leaves other listeners alone', function () {
    Event::listen(MessageSending::class, fn (MessageSending $event) => $event->message->getHeaders()->addTextHeader('X-Before', 'yes'));

    Mail::raw('Hello', fn ($m) => $m->to('to@example.org')->from('info@example.com'));

    expect($this->lastSentRaw())->toContain('X-Before: yes')->toContain('DKIM-Signature');
});
