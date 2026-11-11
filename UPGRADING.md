# Upgrading

If you feel like something is missing from the upgrading guide, feel free to open up a PR on the repository.

# From 3.x to 4.0

Version 4.0 contains breaking changes. Most apps only need the checklist, the sections below explain every change.

## Upgrade with an AI agent

The package ships step by step upgrade instructions for AI agents as [Agent Skill](resources/boost/skills/laravel-dkim-upgrade/SKILL.md).
The agent searches your code, asks you before decisions like the [fallback for mails without matching sender](#mails-without-matching-sender-are-not-signed), updates the package and the config, runs `php artisan dkim:check` and your tests and does not commit.

Paste this prompt into Claude Code, Cursor, Copilot or a similar agent:

```text
Upgrade tobymaxham/laravel-dkim from 3.x to 4.0. Follow the instructions in
https://raw.githubusercontent.com/TobyMaxham/laravel-dkim/main/resources/boost/skills/laravel-dkim-upgrade/SKILL.md
```

With [Laravel Boost](https://laravel.com/docs/boost), the skill is installed with `php artisan boost:install` / `boost:update` once the package is on 4.x. Then ask your agent to "upgrade laravel-dkim".
The prompt above also works without Boost.

Always review the changes before you deploy them.

## Checklist

1. Update to PHP 8.3+ and Laravel 12 or 13. If you can not, stay on `^3.0` (no longer maintained).
2. Run `composer require tobymaxham/laravel-dkim:^4.0 -W`.
3. If you published `config/laravel-dkim.php`: set `dkim_domain` for every sender, move address based sender names to `addresses` and add the [new keys](#config).
4. **Decide about mails whose From address matches no sender** (see [below](#mails-without-matching-sender-are-not-signed)).
5. Remove `dkim_identity` / `MAIL_DKIM_IDENTITY`.
6. Search your code for `DkimSigner` and `RuntimeException` around mail sending (see [API and exceptions](#api-and-exceptions)).
7. Run `php artisan dkim:check`.

## Requirements

| | 3.x | 4.0 |
|---|---|---|
| PHP | 8.0+ | **8.3+** |
| Laravel | 9 – 11 | **12, 13** |
| `ext-openssl` | needed | **required in composer.json** |

## Senders are chosen by domain

In 3.x the sender was chosen by its **name**: the From address with `.` replaced by `_` (e.g. `info@example_com`). This was not documented.
In 4.0 the name is only a name. The sender is chosen by the From address in this order (see the [readme](README.md#multiple-domains)):

1. a sender that lists the address in `addresses`,
2. a sender whose `dkim_domain` is the domain of the From address or a parent domain (the longest wins),
3. the sender without `dkim_domain` (at most one), it signs with the domain of the From address,
4. otherwise `dkim_fallback`.

Before:

```php
'sender' => [
    'default' => [
        'dkim_selector' => 'default',
        'dkim_domain'   => 'example.com',
        // ...
    ],
    'info@example_org' => [
        'dkim_selector' => 'info',
        'dkim_domain'   => 'example.org',
        // ...
    ],
],
```

After:

```php
'sender' => [
    'default' => [
        'dkim_selector' => 'default',
        'dkim_domain'   => 'example.com',
        // ...
    ],
    'example_org' => [
        'dkim_selector' => 'info',
        'dkim_domain'   => 'example.org',
        'addresses'     => ['info@example.org'], // only needed if other addresses of example.org must not use this sender
        // ...
    ],
],
```

A sender without `dkim_domain` signs every From domain that no other sender matches. In 3.x an empty `dkim_domain` threw "Some configuration is missing to add DKIM signature!".

## Mails without matching sender are not signed

In 3.x a mail whose From address matched no sender was signed with the default sender, also for other domains. Such a signature does not help with DMARC, because the domain differs from the From address.
In 4.0 these mails are sent **without signature** (`dkim_fallback=skip`). Choose one option:

| `.env` | Effect |
|--------|--------|
| nothing (`MAIL_DKIM_FALLBACK=skip`) | Mails without matching sender are not signed. Recommended. |
| `MAIL_DKIM_FALLBACK=default` | Same as 3.x: sign them with the sender `dkim_default_sender`. |
| `MAIL_DKIM_FALLBACK=fail` | Throw `NoMatchingSender`, the mail is not sent (with `dkim_on_error=throw`). |

## `dkim_identity` is removed

`dkim_identity` was in the config, but 2.x and 3.x never used it. `symfony/mime` always signs with `i=@{domain}` and has no option to change it. Remove the key from your config and `MAIL_DKIM_IDENTITY` from your `.env` files. A remaining key is ignored.

## `MAIL_DKIM_ENABLED`

The default is `false`, as in the 3.x config file (the 3.x readme said `true`). Set `MAIL_DKIM_ENABLED=true` to sign.

## Config

If you published `config/laravel-dkim.php`, add these keys (or run `php artisan vendor:publish --tag=laravel-dkim-config --force` and set your values again):

```php
'dkim_fallback' => env('MAIL_DKIM_FALLBACK', 'skip'),
'dkim_on_error' => env('MAIL_DKIM_ON_ERROR', 'throw'),

'sender' => [
    'default' => [
        // ...
        'addresses' => [],
        'options'   => [],
    ],
],
```

Missing keys use the defaults. The config is validated now, invalid values throw `InvalidDkimConfiguration` with the config key in the message:

- a selector with other characters than letters, digits, `-` and `.`
- a `dkim_domain` that is no domain name (e.g. with `https://` or `@`)
- a `dkim_algo` other than `rsa-sha256`
- more than one sender without `dkim_domain`
- unknown `options`

`dkim_private_key` can be empty now: then `storage/app/dkim/{domain}.{selector}.pem` is used (the path of `php artisan dkim:generate`). It also accepts the PEM string.

## API and exceptions

Only relevant if you use the classes directly.

- `DkimSigner` is created by the container (scoped) with a new constructor. `signMessage($message, $sender)` is replaced by `sign(Message $message): ?Message`, which returns `null` if the message is not signed. `getSigner()` is removed.
- `ApplyDkimSignature` gets the `DkimSigner` in the constructor.
- Errors throw `TobyMaxham\LaravelDkimSigner\Exceptions\InvalidDkimConfiguration` or `NoMatchingSender`. Both extend `DkimException`, which extends `RuntimeException`, so `catch (RuntimeException $e)` still works. Use `MAIL_DKIM_ON_ERROR=log` to send unsigned mails instead of failing.

## Stay on 3.x

If you run Laravel 9 – 11 or PHP < 8.3, stay on `tobymaxham/laravel-dkim:^3.0`. It is no longer maintained.

# From 2.x to 3.x

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

# From v1 to v2

You can only use **v2** if you upgraded to Laravel **9.x**.<br>
Also at the time of writing, the v2 does not support the **Signer Identity**.<br>
If you are using the v1 with the Signer Identity, you need to wait for some updates. Please feel free to make a pull request to enable the Signer Identity.
