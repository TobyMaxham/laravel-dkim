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

If you are using Laravel with auto discovery you have nothing to do.
Be sure the package Service Provider `TobyMaxham\LaravelDkimSigner\DkimSignerProvider` is registered in your `config/app.php` file.

You can disable the signer by setting the `dkim_signer_enabled` config value to `false`.

```php
<?php

return [
    'dkim_signer_enabled' => env('MAIL_DKIM_ENABLED', true),
];
```

## Credits

- [TobyMaxham](https://github.com/TobyMaxham)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
