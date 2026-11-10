<?php

return [
    /*
     * Sign outgoing mails with DKIM. Off by default.
     */
    'dkim_signer_enabled' => env('MAIL_DKIM_ENABLED', false),

    /*
     * The sender that is used when dkim_fallback is "default".
     */
    'dkim_default_sender' => env('MAIL_DKIM_DEFAULT_SENDER', 'default'),

    /*
     * What to do with a mail whose From address matches no sender:
     * "skip"    - send it without signature (default)
     * "default" - sign it with the dkim_default_sender (behavior of 3.x)
     * "fail"    - throw a NoMatchingSender exception
     */
    'dkim_fallback' => env('MAIL_DKIM_FALLBACK', 'skip'),

    /*
     * What to do when a mail can not be signed (invalid config, missing key, wrong passphrase):
     * "throw" - throw the exception, the mail is not sent (default)
     * "log"   - log a warning and send the mail without signature
     */
    'dkim_on_error' => env('MAIL_DKIM_ON_ERROR', 'throw'),

    /*
     * The senders. A mail is signed by the sender that matches its From address:
     * 1. a sender that lists the address in "addresses"
     * 2. a sender whose "dkim_domain" is the domain of the address or a parent domain (the longest wins)
     * 3. the sender without "dkim_domain" (at most one), it signs for the domain of the address
     * 4. otherwise "dkim_fallback" decides
     */
    'sender' => [
        'default' => [
            /*
             * The DKIM selector, the first part of the DNS record {selector}._domainkey.{domain}. (required)
             */
            'dkim_selector' => env('MAIL_DKIM_SELECTOR', 'default'),

            /*
             * The domain of the signature (d=). It must be the domain of the From address or a parent of it.
             * Leave it empty to sign with the domain of the From address.
             */
            'dkim_domain' => env('MAIL_DKIM_DOMAIN'),

            /*
             * The private key: a path to a PEM file or the PEM string itself.
             * Leave it empty to use storage/app/dkim/{domain}.{selector}.pem (created by "php artisan dkim:generate").
             * Never commit the key and never store it in the public directory.
             */
            'dkim_private_key' => env('MAIL_DKIM_PRIVATE_KEY'),

            /*
             * The signing algorithm. Only "rsa-sha256" is supported.
             */
            'dkim_algo' => env('MAIL_DKIM_ALGO', 'rsa-sha256'),

            /*
             * The passphrase of the private key, if it is encrypted.
             */
            'dkim_passphrase' => env('MAIL_DKIM_PASSPHRASE'),

            /*
             * Exact From addresses for this sender. They win over the domain match.
             */
            'addresses' => [],

            /*
             * Options for the signature (all optional):
             * "header_canon" and "body_canon": "relaxed" (default) or "simple"
             * "signature_expiration_delay": seconds until the signature expires (x=), 0 = never (default)
             * "headers_to_ignore": names of headers that are not signed
             */
            'options' => [],
        ],
    ],
];
