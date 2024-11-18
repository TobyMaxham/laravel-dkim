# Upgrading

If you feel like something is missing from the upgrading guide, feel free to open up a PR on the repository.

## 2.x to 3.x

**!! Warning - Config file has to be changes !!**

In Version 3.x I decided to use multiple configuration with an so called "sender". So it is now possible to use multpile configurations
when you want so send e-mails from multiple accounts/sender.
If you have published the configuration file, **you have to change your configuration file before upgrading**.

First of all, add the following lines to your existing config:
```php
    'dkim_default_sender' => env('MAIL_DKIM_DEFAULT_SENDER', 'default'),

    'sender' => [
        'default' => [
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
             * For more info, see https://www.mailhardener.com/kb/how-to-create-a-dkim-record-with-openssl
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
        ],
    ],
```

Then consider any changes you have to do.
In the "sender" section you can now add another sender.

## From v1 to v2

You can only use **v2** if you upgraded to Laravel **9.x**.<br>
Also at the time of writing, the v2 does not support the **Signer Identity**.<br>
If you are using the v1 with the Signer Identity, you need to wait for some updates. Please feel free to make a pull request to enable the Signer Identity.
