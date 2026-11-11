---
name: laravel-dkim-upgrade
description: Upgrade an app to a new major version of tobymaxham/laravel-dkim (e.g. 3.x to 4.0). Use when the user asks to upgrade, update or migrate laravel-dkim, or when composer.json requires an older major version of tobymaxham/laravel-dkim than the latest one.
---

# Upgrade tobymaxham/laravel-dkim

Step by step instructions for AI agents to upgrade a Laravel app that uses `tobymaxham/laravel-dkim`.
The human readable guide is [UPGRADING.md](https://github.com/TobyMaxham/laravel-dkim/blob/main/UPGRADING.md).

## 1. Find the right guide

1. Read the installed version: `composer show tobymaxham/laravel-dkim` (or `composer.lock`).
2. Pick the guide for the upgrade.

| From | To  | Guide                                                |
|------|-----|------------------------------------------------------|
| 3.x  | 4.0 | [references/3.x-to-4.0.md](references/3.x-to-4.0.md) |

If you read this file from GitHub, the guides are in
`https://raw.githubusercontent.com/TobyMaxham/laravel-dkim/main/resources/boost/skills/laravel-dkim-upgrade/references/`.

## 2. Rules for every upgrade

- **Ask the user before decisions that change behavior in production**, e.g. which mails are signed. Never guess them. Each guide marks these steps with **ASK**.
- Never print, log or commit a private key or a passphrase. Only check whether they are set.
- Do not change `dkim_selector`, `dkim_domain` or the key of an existing sender on your own. The DNS record must match.
- Change `.env.example` and the config, but only tell the user which values to set in `.env` of each environment. Do not edit `.env` files of other environments.
- Only change code that uses this package. Do not refactor unrelated code.
- Do not commit. Leave the changes for the user to review.
- Run the test suite (and static analysis, if the app uses it) after the upgrade and fix failures caused by the upgrade.

## 3. Report

Finish with a short summary for the user:

1. Version before and after.
2. Decisions the user made (or still has to make).
3. Files you changed.
4. Values the user must set or remove in `.env` of each environment (staging, production, ...).
5. Result of `php artisan dkim:check` and things you could not check, e.g. DNS records of other environments.
6. Test results.
