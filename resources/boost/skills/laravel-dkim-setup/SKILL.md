---
name: laravel-dkim-setup
description: "Set up DKIM signing with tobymaxham/laravel-dkim: generate a key, publish the DNS record, configure senders and verify. Use when the user wants DKIM, signed mails or a better mail reputation in a Laravel app."
---

# Set up tobymaxham/laravel-dkim

Step by step instructions for AI agents. Steps marked with **ASK** need a decision of the user.
The human readable guide is the [readme](https://github.com/TobyMaxham/laravel-dkim/blob/main/README.md).

## Rules

- Never print, log or commit a private key or a passphrase. Only show the public DNS record.
- Never store a key in `public/` or in a path that git tracks. Check with `git check-ignore -v <path>`.
- Change `.env.example` and the config, but only tell the user which values to set in `.env` of each environment. Do not edit `.env` files of other environments.
- Do not change `dkim_selector` or the key of an existing sender without asking. The DNS record must match the key.
- Do not commit. Leave the changes for the user to review.

## Step 1: ASK whether the mails are signed already

If the mail server or provider signs the mails for the domain already, the package is not needed. Ask the user, and suggest to look at the headers of a received mail: a `DKIM-Signature` with `d=<their domain>` means the mails are signed. If they are signed, stop.

## Step 2: ASK for the domains and the selector

Find the From addresses of the app: `MAIL_FROM_ADDRESS`, `config/mail.php` and `->from(` / `from:` in `app/`. Ask the user:

- which domains must be signed (the domains of the From addresses, not `APP_URL`),
- which selector to use (default `default`; use a new one if the domain already has a DKIM record with that name).

## Step 3: Install and create the keys

```sh
composer require tobymaxham/laravel-dkim
php artisan dkim:generate example.com --selector=default
```

Run `dkim:generate` once per domain. It stores the key in `storage/app/dkim/{domain}.{selector}.pem` with mode 0600 and never overwrites a key without `--force`. Never use `--force` without asking.
Use `--passphrase` only if the user wants it; the command asks for it, the user has to type it.

For more than one domain, publish the config with `php artisan vendor:publish --tag=laravel-dkim-config` and add a sender per domain under `sender` (`dkim_selector`, `dkim_domain`). Senders without `dkim_private_key` use the path of `dkim:generate`.

## Step 4: Show the DNS record

Show the user the name and value printed by `dkim:generate` (`{selector}._domainkey.{domain}`, `v=DKIM1; k=rsa; p=...`), also the variant split into strings of 255 characters. Never show the private key.

## Step 5: Name the `.env` values

Tell the user to set in every environment that sends mails:

```dotenv
MAIL_DKIM_ENABLED=true
MAIL_DKIM_DOMAIN=example.com
MAIL_DKIM_SELECTOR=default
```

Add the keys without secrets to `.env.example`. Remind the user that the key file must exist on every server that sends mails, including queue workers.

## Step 6: Check

After the DNS record is published, run:

```sh
php artisan dkim:check
```

Every sender must be `OK`. `DNS_MISSING` right after the change can mean the DNS update is not visible yet. Report other statuses with the details column.

## Step 7: Test mail

Suggest that the user sends a test mail to an address they can read and checks that the `DKIM-Signature` header has `d=<domain>` and that the receiver shows `dkim=pass`.

## Report

1. Domains, senders and selectors.
2. DNS records the user must publish (name and value).
3. `.env` values for each environment.
4. Files you changed.
5. Result of `dkim:check`.
