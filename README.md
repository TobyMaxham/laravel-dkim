[![Laravel DKIM][laravel-dkim-image]][laravel-dkim-edit-link]

[![Latest Version on Packagist](https://img.shields.io/packagist/v/tobymaxham/laravel-dkim.svg?style=flat-square)](https://packagist.org/packages/tobymaxham/laravel-dkim)
[![Tests](https://img.shields.io/github/actions/workflow/status/TobyMaxham/laravel-dkim/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/TobyMaxham/laravel-dkim/actions/workflows/tests.yml)
[![MIT Licensed](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/tobymaxham/laravel-dkim.svg?style=flat-square)](https://packagist.org/packages/tobymaxham/laravel-dkim)
[![Support me on Patreon](https://img.shields.io/endpoint.svg?url=https%3A%2F%2Fshieldsio-patreon.vercel.app%2Fapi%3Fusername%3DTobymaxham%26type%3Dpatrons&style=flat)](https://patreon.com/Tobymaxham)


Add DKIM signatures to the mails of your Laravel app, without replacing the mailer.
One domain or many, with commands to create the key and to check the DNS record.

One domain:

```dotenv
MAIL_DKIM_ENABLED=true
MAIL_DKIM_DOMAIN=example.com
MAIL_DKIM_SELECTOR=default
```

Several domains, in `config/laravel-dkim.php`:

```php
'sender' => [
    'default' => ['dkim_selector' => 'default', 'dkim_domain' => 'example.com'],
    'shop'    => ['dkim_selector' => 'shop', 'dkim_domain' => 'shop-example.org'],
],
```

Mails from `info@example.com` are signed with `d=example.com`, mails from `orders@shop-example.org` with `d=shop-example.org`.


## Contents

- [What it does](#what-it-does)
- [When you do not need it](#when-you-do-not-need-it)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quickstart](#quickstart)
- [How it works](#how-it-works)
- [Configuration](#configuration)
- [Multiple domains](#multiple-domains)
- [Fallback and error handling](#fallback-and-error-handling)
- [Commands](#commands)
- [Keys and security](#keys-and-security)
- [Troubleshooting](#troubleshooting)


## What it does

The package listens to Laravel's `Illuminate\Mail\Events\MessageSending` event and adds a `DKIM-Signature` header (RFC 6376, `rsa-sha256`) to every outgoing mail.
It does not replace the mailer or the mail service provider, so your mailers, transports and other mail packages keep working.
Each mail is signed with the sender that matches its From address, so one app can sign mails for several domains.
`php artisan dkim:generate` creates the key and shows the DNS record, `php artisan dkim:check` checks the config, the keys and the DNS records.


## When you do not need it

If your mail server or mail provider already signs your mails with DKIM for your domain, you do not need this package. A second signature for the same domain adds nothing.
The package helps when you send through an SMTP server that does not sign your mails.

To find out, send a mail to an address you can read and look at the headers: a `DKIM-Signature` header with `d=` set to your domain means the mail is already signed.


## Requirements

- PHP 8.3 or higher
- Laravel 12 or 13
- PHP extension `openssl`

Use version `3.x` for older PHP or Laravel versions (no longer maintained).

**Upgrading from 3.x?** Please read the [upgrade guide](UPGRADING.md), or let your [AI agent do the upgrade](UPGRADING.md#upgrade-with-an-ai-agent).


## Installation

```sh
composer require tobymaxham/laravel-dkim
```

The service provider is registered automatically.

To change the config (e.g. for [multiple domains](#multiple-domains)), publish it:

```sh
php artisan vendor:publish --tag=laravel-dkim-config
```


## Quickstart

**1. Create a key** for the domain of your From address:

```sh
php artisan dkim:generate example.com
```

The private key is stored in `storage/app/dkim/example.com.default.pem`. The command shows the DNS record and the values for your `.env`.

**2. Add the DNS record** at your DNS provider, as shown by the command:

| Type | Name | Value |
|------|------|-------|
| TXT | `default._domainkey.example.com` | `v=DKIM1; k=rsa; p=MIIBIjANBgkq...` |

**3. Enable signing** in your `.env`:

```dotenv
MAIL_DKIM_ENABLED=true
MAIL_DKIM_DOMAIN=example.com
MAIL_DKIM_SELECTOR=default
```

**4. Check** the key and the DNS record (DNS changes can take a while):

```sh
php artisan dkim:check
```


## How it works

- When Laravel sends a mail, it fires `MessageSending`. The listener `TobyMaxham\LaravelDkimSigner\Listener\ApplyDkimSignature` picks the [sender](#multiple-domains) for the From address and adds the signature to the message. Then Laravel hands the message to the transport.
- Queued mails are signed when they are sent, so in the queue worker. The worker needs access to the private key.
- The signature covers the body and the headers, including `Date` and `Message-ID`. If the message has no `Date` or `Message-ID` yet, they are created when it is signed and added to the message, so they do not change on send. `Bcc` is never signed and the mail still reaches all Bcc recipients.
- Changes to the message after signing (e.g. by another `MessageSending` listener that runs later) make the signature invalid.
- A mail that already has a signature with the same domain and selector is not signed again.

The test suite verifies the signatures of the sent mails with the public key (plain text, HTML with attachment, both canonicalizations).


## Configuration

The config file `config/laravel-dkim.php` explains every option. Global options:

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `dkim_signer_enabled` | `MAIL_DKIM_ENABLED` | `false` | Sign outgoing mails. |
| `dkim_default_sender` | `MAIL_DKIM_DEFAULT_SENDER` | `default` | The sender for `dkim_fallback=default`. |
| `dkim_fallback` | `MAIL_DKIM_FALLBACK` | `skip` | What to do when no sender matches, see [Fallback](#fallback-and-error-handling). |
| `dkim_on_error` | `MAIL_DKIM_ON_ERROR` | `throw` | What to do when a mail can not be signed, see [Error handling](#fallback-and-error-handling). |

Options per sender (in `sender.<name>`; the env variables are used by the `default` sender):

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `dkim_selector` | `MAIL_DKIM_SELECTOR` | `default` | Required. The selector, the first part of the DNS name `{selector}._domainkey.{domain}`. Letters, digits, `-` and `.`. |
| `dkim_domain` | `MAIL_DKIM_DOMAIN` | `null` | The domain of the signature (`d=`). Empty: the domain of the From address. |
| `dkim_private_key` | `MAIL_DKIM_PRIVATE_KEY` | `null` | Path to a PEM file or the PEM string. Empty: `storage/app/dkim/{domain}.{selector}.pem`. |
| `dkim_passphrase` | `MAIL_DKIM_PASSPHRASE` | `null` | The passphrase of an encrypted key. |
| `dkim_algo` | `MAIL_DKIM_ALGO` | `rsa-sha256` | The algorithm. Only `rsa-sha256` is supported. |
| `addresses` | | `[]` | Exact From addresses for this sender (also a comma separated string). |
| `options` | | `[]` | `header_canon` and `body_canon` (`relaxed` (default) or `simple`), `signature_expiration_delay` (seconds, `0` = never), `headers_to_ignore` (header names that are not signed). |

The config is validated when a mail is signed. Invalid values throw an `InvalidDkimConfiguration` exception that names the config key, e.g. `Invalid DKIM configuration [laravel-dkim.sender.default.dkim_selector]: ...`.
The message never contains the key or the passphrase.


## Multiple domains

Each mail is signed by one sender. The sender is chosen by the first From address of the mail, in this order:

1. **Address:** the sender that lists the From address in `addresses`.
2. **Domain:** the sender whose `dkim_domain` is the domain of the From address or a parent domain of it, e.g. `example.com` for `info@mail.example.com`. If several match, the longest `dkim_domain` wins; for the same `dkim_domain` the first sender in the config wins.
3. **Any domain:** the sender without `dkim_domain`. It signs with the domain of the From address. Only one sender may have no `dkim_domain`.
4. **Fallback:** otherwise `dkim_fallback` decides.

Upper and lower case do not matter. The name of a sender is only a name, it is not compared with the From address.

```php
'sender' => [
    'default' => [
        'dkim_selector' => env('MAIL_DKIM_SELECTOR', 'default'),
        'dkim_domain'   => env('MAIL_DKIM_DOMAIN'), // example.com
    ],

    'shop' => [
        'dkim_selector'    => 'shop',
        'dkim_domain'      => 'shop-example.org',
        'dkim_private_key' => env('MAIL_DKIM_SHOP_KEY'),
    ],

    'newsletter' => [
        'dkim_selector' => 'news',
        'dkim_domain'   => 'example.com',
        'addresses'     => ['news@example.com'],
    ],
],
```

| From | Sender | Signature |
|------|--------|-----------|
| `news@example.com` | `newsletter` (address) | `d=example.com; s=news` |
| `info@example.com` | `default` (domain) | `d=example.com; s=default` |
| `info@mail.example.com` | `default` (parent domain) | `d=example.com; s=default` |
| `orders@shop-example.org` | `shop` (domain) | `d=shop-example.org; s=shop` |
| `info@other.net` | none | depends on `dkim_fallback` |

Create a key and a DNS record for each sender:

```sh
php artisan dkim:generate shop-example.org --selector=shop
php artisan dkim:generate example.com --selector=news
```

Senders without `dkim_private_key` use `storage/app/dkim/{domain}.{selector}.pem`, the path `dkim:generate` writes to.


## Fallback and error handling

`dkim_fallback` (`MAIL_DKIM_FALLBACK`) decides about mails whose From address matches no sender (and mails without From address):

| Value | Behavior |
|-------|----------|
| `skip` (default) | The mail is sent without signature. |
| `default` | The mail is signed with the sender `dkim_default_sender`. This was the behavior of 3.x. A signature with another domain than the From address does not help with DMARC. |
| `fail` | A `NoMatchingSender` exception is thrown. |

`dkim_on_error` (`MAIL_DKIM_ON_ERROR`) decides what happens when a mail can not be signed, e.g. because of an invalid config, a missing key, a wrong passphrase or `dkim_fallback=fail`:

| Value | Behavior |
|-------|----------|
| `throw` (default) | The exception is thrown and the mail is **not sent**. |
| `log` | A warning is logged (with the sender name, without secrets) and the mail is sent without signature. |

All exceptions extend `TobyMaxham\LaravelDkimSigner\Exceptions\DkimException` (a `RuntimeException`).
An invalid value of `dkim_on_error` itself always throws.


## Commands

### `dkim:generate`

```sh
php artisan dkim:generate {domain?} {--selector=} {--bits=2048} {--path=} {--passphrase} {--force}
```

Creates an RSA key pair. Without a domain or selector it uses `dkim_domain` and `dkim_selector` of the default sender.

- The private key is written to `--path` or `storage/app/dkim/{domain}.{selector}.pem` with mode `0600` (a new directory gets `0700`). It is never printed.
- An existing file is never overwritten without `--force`.
- `--passphrase` asks for a passphrase and encrypts the key (AES-256).
- `--bits` accepts 1024 to 4096. Keys below 2048 bits print a warning.
- A path in the `public` directory prints a warning.

```text
   INFO  Private key written to storage/app/dkim/example.com.default.pem (mode 0600). Never commit it.

Add this TXT record to the DNS of example.com:

  Name:  default._domainkey.example.com
  Value: v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAxPwEXnYWdTjjNTf4gYtRXoB8...

If your DNS provider only accepts strings up to 255 characters, enter the value as these strings:

  "v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAxPwEXnYW..." "ZTkLAmLYdyoqDotzCJ57..."

Add these values to your .env:

  MAIL_DKIM_ENABLED=true
  MAIL_DKIM_DOMAIN=example.com
  MAIL_DKIM_SELECTOR=default

Run "php artisan dkim:check" when the DNS record is published.
```

### `dkim:check`

```sh
php artisan dkim:check {sender?} {--domain=} {--no-dns}
```

Checks all senders (or the given one): the config, the private key and the passphrase, and whether the TXT record `{selector}._domainkey.{domain}` contains the public key of the private key.

```text
+-----------+------------------+----------+-------------+-----------------------------------------------------------------------+
| Sender    | Domain           | Selector | Status      | Details                                                               |
+-----------+------------------+----------+-------------+-----------------------------------------------------------------------+
| default   | example.com      | default  | OK          | Key is valid and matches the DNS record at default._domainkey.exam... |
| shop      | shop-example.org | shop     | DNS_MISSING | No TXT record found at shop._domainkey.shop-example.org.              |
+-----------+------------------+----------+-------------+-----------------------------------------------------------------------+
```

| Status | Meaning |
|--------|---------|
| `OK` | Config and key are valid, the DNS record matches (unless `--no-dns`). |
| `SKIPPED` | The sender has no `dkim_domain` and no key. Pass `--domain=<domain>` to check it. |
| `CONFIG_ERROR` | The config of the sender is invalid. |
| `KEY_ERROR` | The key is missing, not readable, no RSA key or the passphrase is wrong. |
| `DNS_MISSING` | There is no TXT record. |
| `DNS_MISMATCH` | The public key in the DNS record does not match the private key. |
| `DNS_INVALID` | The TXT record is no valid DKIM record (e.g. wrong `v=` or `k=`, empty `p=`, two DKIM records). |

The exit code is `1` if a sender has another status than `OK` or `SKIPPED`, or if a global option is invalid. Use it in your deployment.
`--no-dns` only checks the config and the keys. For the sender without `dkim_domain`, `--domain=example.com` checks the key and record of that domain.


## Keys and security

- **Never commit the private key** and never store it in the `public` directory. The default path `storage/app/dkim` is outside `public`, and in a new Laravel 12 or 13 app `storage/app/.gitignore` already ignores it. Check this in your app.
- Keep the key file readable only for the user that sends the mails (`dkim:generate` uses mode `0600`). Remember the queue workers and every server that sends mails.
- `dkim_private_key` accepts a file path or the PEM string itself (starting with `-----BEGIN`), e.g. from a secret store. A file is easier to handle than a PEM string in `.env`.
- Use a passphrase (`--passphrase`, `MAIL_DKIM_PASSPHRASE`) if the key file may be copied, e.g. in backups.
- **Rotate keys with a new selector**: create a key with a new selector (`dkim:generate example.com --selector=2026-11`), publish its DNS record, check it with `dkim:check`, then change `MAIL_DKIM_SELECTOR`. Remove the old DNS record a few days later, when no mails signed with the old key are on their way any more.

Without the command, you can create a key with OpenSSL:

```sh
openssl genrsa -out example.com.default.pem 2048
openssl rsa -in example.com.default.pem -pubout -outform der | openssl base64 -A
```

The second command prints the value for `p=` in the DNS record `v=DKIM1; k=rsa; p=...`.


## Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| No `DKIM-Signature` header | `MAIL_DKIM_ENABLED` is not `true`, or no sender matches the From address and `dkim_fallback` is `skip` | Enable it. Set `dkim_domain` or `addresses` of a sender for the From address. |
| `dkim:check` shows `DNS_MISSING` | The TXT record is missing, has a wrong name, or is not published yet | The name is `{selector}._domainkey.{domain}`. If your DNS provider adds the domain itself, enter only `{selector}._domainkey`. Wait for the DNS update. |
| `dkim:check` shows `DNS_MISMATCH` | The DNS record belongs to another key, e.g. after `--force` | Publish the record shown by `dkim:generate` for the current key, or use a new selector. |
| `dkim:check` shows `DNS_INVALID` | The record is broken, e.g. cut off at 255 characters | Enter the value as several strings, as shown by `dkim:generate`. |
| Selector is invalid | The selector contains `_`, spaces or other characters | Use letters, digits, `-` and `.`. |
| Receivers report a domain mismatch (DMARC) | `dkim_domain` is not the domain of the From address or a parent of it | Use a sender with the domain of the From address. Do not use `dkim_fallback=default` for foreign domains. |
| `The private key is encrypted, but no passphrase is set` / `The passphrase of the private key is wrong` | `MAIL_DKIM_PASSPHRASE` is missing or wrong | Set the right passphrase. |
| `The private key file "..." does not exist or is not readable` | Wrong path, or the user of the web server or queue worker can not read the file | Fix the path or the file permissions. Without `dkim_private_key` the path is `storage/app/dkim/{domain}.{selector}.pem`. |
| Mails are not sent any more | `dkim_on_error=throw` and the mail can not be signed | Fix the error (run `dkim:check`), or use `MAIL_DKIM_ON_ERROR=log` to send unsigned mails meanwhile. |


## Testing

```bash
composer test
composer test-coverage
composer analyse
```


## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.


## Security Vulnerabilities

Please see the [security policy](SECURITY.md) on how to report a vulnerability. Do not use the issue tracker.


## Support me

[![ko-fi](https://ko-fi.com/img/githubbutton_sm.svg)](https://ko-fi.com/Z8Z4NZKU)<br>
[![Support me on Patreon](https://img.shields.io/endpoint.svg?url=https%3A%2F%2Fshieldsio-patreon.vercel.app%2Fapi%3Fusername%3DTobymaxham%26type%3Dpatrons&style=flat)](https://patreon.com/Tobymaxham)


## Credits

- [TobyMaxham](https://github.com/TobyMaxham)
- [Florian Jahn](https://github.com/fjahn) (the idea for the default domain and key path, #1)
- [All Contributors](../../contributors)


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.


[laravel-dkim-image]: https://socialify.git.ci/TobyMaxham/laravel-dkim/image?description=1&font=Raleway&issues=1&language=1&owner=1&pattern=Charlie%20Brown&pulls=1&stargazers=1&theme=Light
[laravel-dkim-edit-link]: https://socialify.git.ci/TobyMaxham/laravel-dkim?description=1&font=Raleway&issues=1&language=1&owner=1&pattern=Charlie%20Brown&pulls=1&stargazers=1&theme=Light
