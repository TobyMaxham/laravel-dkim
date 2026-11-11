# Changelog

All notable changes to `tobymaxham/laravel-dkim` will be documented in this file

## 4.0.0 - Unreleased

Major release with breaking changes. Please read the [upgrade guide](UPGRADING.md).

> **Upgrade with an AI agent:** paste this prompt into Claude Code, Cursor, Copilot or a similar agent:
>
> ```text
> Upgrade tobymaxham/laravel-dkim from 3.x to 4.0. Follow the instructions in
> https://raw.githubusercontent.com/TobyMaxham/laravel-dkim/main/resources/boost/skills/laravel-dkim-upgrade/SKILL.md
> ```

### Breaking Changes

- Requires PHP 8.3+ and Laravel 12 or 13 (dropped PHP 8.0 - 8.2 and Laravel 9 - 11)
- The sender is chosen by the domain of the From address (`dkim_domain`) or by exact address (`addresses`). Sender names like `info@example_com` are no longer compared with the From address
- Mails without matching sender are sent without signature (`dkim_fallback=skip`). Before, they were signed with the default sender. Use `MAIL_DKIM_FALLBACK=default` for the old behavior
- Removed `dkim_identity`: it was never used, `symfony/mime` always signs with `i=@{domain}`
- Errors throw `InvalidDkimConfiguration` / `NoMatchingSender` (both extend `DkimException`, a `RuntimeException`) with the config key in the message, instead of a `RuntimeException` with a generic message
- `DkimSigner` has a new API: `sign(Message $message): ?Message`, it is created by the container (scoped)
- `dkim_algo` only accepts `rsa-sha256`
- The config file is published with the tag `laravel-dkim-config`
- All classes use `declare(strict_types=1)`

### Added

- Domain based sender resolution with relaxed alignment (subdomains) and longest match
- A sender without `dkim_domain` signs with the domain of the From address
- Default key path `storage/app/dkim/{domain}.{selector}.pem` when `dkim_private_key` is empty
- `dkim_private_key` accepts a PEM string as well as a file path
- `addresses` per sender for exact From addresses
- `dkim_fallback` (`skip`, `default`, `fail`) and `dkim_on_error` (`throw`, `log`)
- `options` per sender: `header_canon`, `body_canon`, `signature_expiration_delay`, `headers_to_ignore`
- Validation of the config and the key (selector, domain, algorithm, readable RSA key, passphrase) with clear messages without secrets
- `php artisan dkim:generate` to create a key pair and show the DNS record
- `php artisan dkim:check` to check the config, the keys and the DNS records
- A mail that is already signed with the same domain and selector is not signed again
- [Agent Skills](resources/boost/skills) for setup and upgrade and a [Laravel Boost guideline](resources/boost/guidelines/core.blade.php)
- Test suite (Pest, 100 % coverage) with verification of the sent signatures, PHPStan level 8, Pint and GitHub Actions

### Fixed

- **Bcc recipients were dropped** when a mail was signed, because all headers were replaced with the signed copy, which has no `Bcc` header
- Mails of other domains were silently signed with the domain and key of the default sender (breaks DMARC alignment)
- The documented default of `MAIL_DKIM_ENABLED` was `true`, the config used `false`. It is `false` now everywhere
- The readme derived the public key with `openssl ec`, which fails for RSA keys with some OpenSSL versions (e.g. LibreSSL). It uses `openssl rsa` now
- A missing config or key threw a generic message without the reason

### Changed

- Contact email changed to `git@maxham.net`
- Pint replaces PHP-CS-Fixer

### Credits

- Based on an idea from #1 by Florian Jahn (default domain and default key path)

## 3.x - 2024-11-18

- Added support for DKIM signing in Laravel 11.x
- Added a "sender"-based DKIM signing, so you can add multiple DKIM Signatures in the same application without added your own services.
- Some improvements to the documentation

## 2.x - 2022-04-10

- This Update is for Laravel 9.x since Laravel 9 uses Symfony Mailer instead of Swift Mailer.
- Added support for DKIM signing in Laravel 9.x
- Added a configuration to disable DKIM signing ```MAIL_DKIM_ENABLED=false``` (default is true)

