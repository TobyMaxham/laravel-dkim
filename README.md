# TobyMaxham Laravel DKIM

[![Latest Version on Packagist](https://img.shields.io/packagist/v/tobymaxham/laravel-dkim.svg?style=flat-square)](https://packagist.org/packages/tobymaxham/laravel-dkim)
[![Total Downloads](https://img.shields.io/packagist/dt/tobymaxham/laravel-dkim.svg?style=flat-square)](https://packagist.org/packages/tobymaxham/laravel-dkim)

This package can be used to add a DKIM signature to your outgoing mails.

Instead of changing the default Laravel Mailer, Laravel DKIM will listen for the `\Illuminate\Mail\Events\MessageSending`
Event and then call the `\TobyMaxham\LaravelDkimSigner\Listener\ApplyDkimSignature` Listener.

This keeps your Laravel Mailer behavior or any changes you have done inside your projects.

## Installation

You can install the package via composer:

```bash
composer require tobymaxham/laravel-dkim
```

## Usage

```php
```

## Credits

- [TobyMaxham](https://github.com/TobyMaxham)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
