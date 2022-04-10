<?php

namespace TobyMaxham\LaravelDkimSigner;

use Symfony\Component\Mime\Crypto\DkimOptions;

class DkimSigner
{
    protected \Illuminate\Contracts\Foundation\Application $app;

    private array $requiredConfig = [
        'dkim_selector',
        'dkim_domain',
        'dkim_private_key',
    ];

    public function __construct(\Illuminate\Contracts\Foundation\Application $app)
    {
        $this->app = $app;
    }

    public function signMessage(\Symfony\Component\Mime\Message $message)
    {
        if (! $this->app['config']->get('laravel-dkim.dkim_signer_enabled')) {
            return $message;
        }

        $signer = $this->getSigner();
        if ( $signer instanceof \Symfony\Component\Mime\Crypto\DkimSigner) {
            $message = $signer->sign($message, $this->getOptions()->toArray());
        }

        return $message;
    }

    public function getSigner()
    {
        throw_if(
            ! $this->hasRequiredConfig(),
            'Some configuration is missing to add DKIM signature!'
        );

        throw_if(
            !is_file($keyfile = $this->app['config']->get('laravel-dkim.dkim_private_key')),
            'Invalid file: '.$keyfile
        );

        $signer = new \Symfony\Component\Mime\Crypto\DkimSigner(
            'file://'.$keyfile,
            $this->app['config']->get('laravel-dkim.dkim_domain'),
            $this->app['config']->get('laravel-dkim.dkim_selector'),
            [],
            $this->app['config']->get('laravel-dkim.dkim_passphrase') ?? ''
        );

        return $signer;
    }

    private function hasRequiredConfig(): bool
    {
        $config = $this->app['config']->get('laravel-dkim');

        return count($this->requiredConfig) === collect($config)
                ->only($this->requiredConfig)
                ->filter()
                ->count();
    }

    private function getOptions(): DkimOptions
    {
        $options = new DkimOptions();

        $algo = $this->app['config']->get('laravel-dkim.dkim_algo', 'rsa-sha256');
        if (empty($algo)) {
            $algo = 'rsa-sha256';
        }

        $options->algorithm($algo);

        return $options;
    }
}
