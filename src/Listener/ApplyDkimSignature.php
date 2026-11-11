<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelDkimSigner\Listener;

use Symfony\Component\Mime\Message;
use Illuminate\Mail\Events\MessageSending;
use TobyMaxham\LaravelDkimSigner\DkimSigner;

class ApplyDkimSignature
{
    public function __construct(protected DkimSigner $signer) {}

    public function handle(MessageSending $event): void
    {
        $signed = $this->signer->sign($event->message);

        if (null !== $signed) {
            $this->applyHeaders($event->message, $signed);
        }
    }

    /**
     * Copy the new signature and the headers it covers (e.g. Date and Message-ID) to the message.
     * The other headers stay untouched, e.g. Bcc, which the signed copy does not contain.
     */
    protected function applyHeaders(Message $message, Message $signed): void
    {
        $headers = $message->getHeaders();
        $signatures = [];

        foreach ($signed->getHeaders()->all() as $header) {
            if ('dkim-signature' === strtolower($header->getName())) {
                $signatures[] = $header;
            } elseif (! $headers->has($header->getName())) {
                $headers->add($header);
            }
        }

        // Symfony appends the new signature after existing ones
        $headers->add(end($signatures) ?: throw new \LogicException('The signed message has no DKIM-Signature.'));
    }
}
