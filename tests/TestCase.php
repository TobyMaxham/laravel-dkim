<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelDkimSigner\Tests;

use Illuminate\Support\Collection;
use Symfony\Component\Mailer\SentMessage;
use Illuminate\Mail\Transport\ArrayTransport;
use Orchestra\Testbench\TestCase as Orchestra;
use TobyMaxham\LaravelDkimSigner\DkimSignerProvider;
use TobyMaxham\LaravelDkimSigner\Tests\Fixtures\Keys;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [DkimSignerProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('mail.default', 'array');
        $app['config']->set('mail.from', ['address' => 'info@example.com', 'name' => 'Example']);
    }

    protected function tearDown(): void
    {
        Keys::cleanup();

        parent::tearDown();
    }

    /**
     * Set the config of one sender (merged into the existing one).
     *
     * @param  array<string, mixed>  $config
     */
    protected function setSender(string $name, array $config): void
    {
        config()->set('laravel-dkim.sender.'.$name, array_merge(
            (array) config('laravel-dkim.sender.'.$name, []),
            $config,
        ));
    }

    /**
     * The messages sent with the array transport.
     *
     * @return Collection<int, SentMessage>
     */
    protected function sentMessages(): Collection
    {
        /** @var ArrayTransport $transport */
        $transport = app('mailer')->getSymfonyTransport();

        return $transport->messages();
    }

    protected function lastSentRaw(): string
    {
        $message = $this->sentMessages()->last();

        $this->assertInstanceOf(SentMessage::class, $message, 'No message was sent.');

        return $message->toString();
    }
}
