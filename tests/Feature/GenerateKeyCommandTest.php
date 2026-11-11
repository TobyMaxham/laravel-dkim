<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use TobyMaxham\LaravelDkimSigner\Key\PrivateKey;
use TobyMaxham\LaravelDkimSigner\Config\SenderConfig;

beforeEach(function () {
    File::deleteDirectory(storage_path('app/dkim'));
    File::deleteDirectory(public_path('dkim'));
});

afterEach(function () {
    File::deleteDirectory(storage_path('app/dkim'));
    File::deleteDirectory(public_path('dkim'));
});

function generate(array $parameters): array
{
    $code = Artisan::call('dkim:generate', $parameters);

    return [$code, Artisan::output()];
}

function recordOf(string $output): string
{
    preg_match('/Value: (v=DKIM1; k=rsa; p=\S+)/', $output, $matches);

    return $matches[1] ?? '';
}

it('creates a key at the default path and shows the DNS record', function () {
    [$code, $output] = generate(['domain' => 'Example.com']);

    $path = storage_path('app/dkim/example.com.default.pem');
    $pem = (string) file_get_contents($path);
    $publicKey = PrivateKey::load(new SenderConfig('default', 'default', 'example.com', $path, null))->publicKey();

    expect($code)->toBe(0)
        ->and(fileperms($path) & 0777)->toBe(0600)
        ->and(fileperms(dirname($path)) & 0777)->toBe(0700)
        ->and($pem)->toStartWith('-----BEGIN PRIVATE KEY-----')
        ->and($output)->toContain('Name:  default._domainkey.example.com')
        ->and(recordOf($output))->toBe('v=DKIM1; k=rsa; p='.$publicKey)
        ->and($output)->toContain("MAIL_DKIM_ENABLED=true\n  MAIL_DKIM_DOMAIN=example.com\n  MAIL_DKIM_SELECTOR=default")
        ->and($output)->not->toContain('MAIL_DKIM_PRIVATE_KEY')
        ->and($output)->not->toContain('PRIVATE KEY')
        ->and($output)->toContain('dkim:check');

    // no line of the private key is printed
    foreach (array_slice(explode("\n", trim($pem)), 1, -1) as $line) {
        expect($output)->not->toContain($line);
    }
});

it('splits the record into strings of 255 characters', function () {
    [, $output] = generate(['domain' => 'example.com', '--bits' => 4096]);

    preg_match('/^  ("[^"]+"(?: "[^"]+")+)$/m', $output, $matches);
    preg_match_all('/"([^"]+)"/', $matches[1] ?? '', $chunks);

    expect($chunks[1])->toHaveCount(3)
        ->and(max(array_map('strlen', $chunks[1])))->toBe(255)
        ->and(implode('', $chunks[1]))->toBe(recordOf($output));
});

it('never overwrites a key without --force', function () {
    generate(['domain' => 'example.com']);
    $path = storage_path('app/dkim/example.com.default.pem');
    $before = file_get_contents($path);

    [$code, $output] = generate(['domain' => 'example.com']);

    expect($code)->toBe(1)
        ->and($output)->toContain('already exists. Use --force to overwrite it.')
        ->and(file_get_contents($path))->toBe($before);

    expect(generate(['domain' => 'example.com', '--force' => true])[0])->toBe(0)
        ->and(file_get_contents($path))->not->toBe($before);
});

it('writes the key to a custom path', function () {
    $path = storage_path('app/dkim/custom/key.pem');

    [$code, $output] = generate(['domain' => 'example.com', '--selector' => 'mail', '--path' => $path]);

    expect($code)->toBe(0)
        ->and($path)->toBeReadableFile()
        ->and($output)->toContain('Name:  mail._domainkey.example.com')
        ->and($output)->toContain('MAIL_DKIM_PRIVATE_KEY='.$path);
});

it('uses domain and selector of the default sender', function () {
    config()->set('laravel-dkim.sender.default.dkim_domain', 'example.org');
    config()->set('laravel-dkim.sender.default.dkim_selector', 'Mail');

    [$code, $output] = generate([]);

    expect($code)->toBe(0)
        ->and(storage_path('app/dkim/example.org.mail.pem'))->toBeReadableFile()
        ->and($output)->toContain('Name:  mail._domainkey.example.org');
});

it('encrypts the key with a passphrase', function () {
    $this->artisan('dkim:generate', ['domain' => 'example.com', '--passphrase' => true])
        ->expectsQuestion('Passphrase for the private key', 'my-secret')
        ->expectsQuestion('Repeat the passphrase', 'my-secret')
        ->expectsOutputToContain('MAIL_DKIM_PASSPHRASE=<your passphrase>')
        ->doesntExpectOutputToContain('my-secret')
        ->assertSuccessful();

    $path = storage_path('app/dkim/example.com.default.pem');

    expect((string) file_get_contents($path))->toContain('ENCRYPTED')
        ->and(PrivateKey::load(new SenderConfig('default', 'default', 'example.com', $path, 'my-secret')))->toBeInstanceOf(PrivateKey::class);
});

it('rejects passphrases that do not match', function () {
    $this->artisan('dkim:generate', ['domain' => 'example.com', '--passphrase' => true])
        ->expectsQuestion('Passphrase for the private key', 'a')
        ->expectsQuestion('Repeat the passphrase', 'b')
        ->expectsOutputToContain('The passphrases are empty or do not match.')
        ->assertFailed();

    expect(storage_path('app/dkim/example.com.default.pem'))->not->toBeFile();
});

it('rejects an empty passphrase', function () {
    $this->artisan('dkim:generate', ['domain' => 'example.com', '--passphrase' => true])
        ->expectsQuestion('Passphrase for the private key', '')
        ->expectsOutputToContain('The passphrases are empty or do not match.')
        ->assertFailed();
});

it('rejects invalid input', function (array $parameters, string $message) {
    [$code, $output] = generate($parameters);

    expect($code)->toBe(1)
        ->and($output)->toContain($message);
})->with([
    'no domain'        => [[], 'Pass the domain of your From addresses'],
    'invalid domain'   => [['domain' => 'https://example.com'], '"https://example.com" is not a valid domain name.'],
    'invalid selector' => [['domain' => 'example.com', '--selector' => 'my_selector'], '"my_selector" is not a valid selector.'],
    'too small'        => [['domain' => 'example.com', '--bits' => 512], 'The key size must be between 1024 and 4096 bits.'],
    'too large'        => [['domain' => 'example.com', '--bits' => 8192], 'The key size must be between 1024 and 4096 bits.'],
    'not a number'     => [['domain' => 'example.com', '--bits' => 'many'], 'The key size must be between 1024 and 4096 bits.'],
]);

it('warns about small keys', function () {
    [$code, $output] = generate(['domain' => 'example.com', '--bits' => 1024]);

    expect($code)->toBe(0)->and($output)->toContain('Keys with less than 2048 bits are not recommended.');
});

it('warns when the key is in the public directory', function () {
    [$code, $output] = generate(['domain' => 'example.com', '--path' => public_path('dkim/key.pem')]);

    expect($code)->toBe(0)->and($output)->toContain('The private key is in the public directory.');
});

it('does not warn outside the public directory', function () {
    [, $output] = generate(['domain' => 'example.com']);

    expect($output)->not->toContain('public directory');
});

it('reports a path that can not be written', function () {
    $readonly = storage_path('app/dkim/readonly');
    mkdir(dirname($readonly), 0700, true);
    mkdir($readonly, 0500);

    try {
        [$code, $output] = generate(['domain' => 'example.com', '--path' => $readonly.'/key.pem']);
        [$dirCode] = generate(['domain' => 'example.com', '--path' => $readonly.'/sub/key.pem']);
    } finally {
        chmod($readonly, 0700);
    }

    expect($code)->toBe(1)
        ->and($output)->toContain('Unable to write the private key to')
        ->and($dirCode)->toBe(1);
})->skip(fn () => 0 === posix_geteuid(), 'root can write everywhere');

it('uses the default key path of the sender config', function () {
    generate(['domain' => 'example.com', '--selector' => 'mail']);

    expect(SenderConfig::defaultKeyPath('example.com', 'mail'))->toBeReadableFile();
});
