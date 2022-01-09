<?php

namespace TobyMaxham\LaravelDkimSigner\Listener;

use Illuminate\Mail\Events\MessageSending;
use TobyMaxham\LaravelDkimSigner\DkimSigner;
use function config;

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
        if (!$message instanceof \Swift_Message) {
            return;
        }

        $signer = app(DkimSigner::class)->getSigner();
        if ( $signer instanceof \Swift_Signers_DKIMSigner) {
            $message->attachSigner($signer);
        }
    }
}
