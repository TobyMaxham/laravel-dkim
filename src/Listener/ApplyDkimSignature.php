<?php

namespace TobyMaxham\LaravelDkimSigner\Listener;

use Illuminate\Mail\Events\MessageSending;
use TobyMaxham\LaravelDkimSigner\DkimSigner;

class ApplyDkimSignature
{
    /**
     * Handle the event.
     *
     * @param  \Illuminate\Mail\Events\MessageSending  $event
     * @return void
     */
    public function handle(MessageSending $event)
    {
        $message = $event->message;
        if (! $message instanceof \Symfony\Component\Mime\Message) {
            return;
        }

        $signedEmail = app(DkimSigner::class)->signMessage($message);
        if ($signedEmail instanceof \Symfony\Component\Mime\Message) {
            $message->setHeaders($signedEmail->getHeaders());
        }
    }
}
