<?php

namespace TobyMaxham\LaravelDkimSigner\Listener;

use Illuminate\Mail\Events\MessageSending;
use TobyMaxham\LaravelDkimSigner\DkimSigner;

class ApplyDkimSignature
{
    /**
     * Handle the event.
     *
     * @param $event
     * @return void
     */
    public function handle(\Illuminate\Mail\Events\MessageSending $event)
    {
        $message = $event->message;
        if (! $message instanceof \Symfony\Component\Mime\Message) {
            return;
        }

        $signedEmail = app(DkimSigner::class)->signMessage($message, $this->getSender($message));
        if ($signedEmail instanceof \Symfony\Component\Mime\Message) {
            $message->setHeaders($signedEmail->getHeaders());
        }
    }

    private function getSender(\Symfony\Component\Mime\Message $message): ?string
    {
        $from = $message->getFrom();
        if (! is_array($from)
            || empty($from)
            || ! isset($from[0])
            || ! $from[0] instanceof \Symfony\Component\Mime\Address) {
            return null;
        }

        return $from[0]->getAddress();
    }
}
