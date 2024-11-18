# Changelog

All notable changes to `tobymaxham/laravel-dkim` will be documented in this file

## 3.x - 2024-11-18

- Added support for DKIM signing in Laravel 11.x
- Added a "sender"-based DKIM signing, so you can add multiple DKIM Signatures in the same application without added your own services.
- Some improvements to the documentation

## 2.x - 2022-04-10

- This Update is for Laravel 9.x since Laravel 9 uses Symfony Mailer instead of Swift Mailer.
- Added support for DKIM signing in Laravel 9.x
- Added a configuration to disable DKIM signing ```MAIL_DKIM_ENABLED=false``` (default is true)

