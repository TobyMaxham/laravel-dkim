<?php

return [

    'dkim_signer_enabled' => env('MAIL_DKIM_ENABLED', true),

    /*
     * The DKIM selector for your outgoing mail server. (required)
     * For more info, see https://dmarcly.com/blog/what-is-dkim-selector-and-how-does-it-work-dkim-selector-explained
     */
    'dkim_selector' => env('MAIL_DKIM_SELECTOR', 'default'),

    /*
     * The domain name for your outgoing mails. (required)
     */
    'dkim_domain' => env('MAIL_DKIM_DOMAIN'),

    /*
     * The path to your private key to sign the outgoing mails. (required)
     */
    'dkim_private_key' => env('MAIL_DKIM_PRIVATE_KEY'),

    /*
     * The signing algorithm. (required)
     * The default value for this is rsa-sha256
     */
    'dkim_algo' => env('MAIL_DKIM_ALGO', 'rsa-sha256'),

    /*
     * If using a signer identity, add it here. (optional)
     */
    'dkim_identity' => env('MAIL_DKIM_IDENTITY'),

    /*
     * The passphrase for your private key. (optional)
     * This is highly recommended. If you do not use a passphrase, leave blank.
     */
    'dkim_passphrase' => env('MAIL_DKIM_PASSPHRASE'),
];
