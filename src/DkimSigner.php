<?php

namespace TobyMaxham\LaravelDkimSigner;

use Illuminate\Support\Arr;

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


        $signer = new \Swift_Signers_DKIMSigner(
            file_get_contents($keyfile),
            $this->app['config']->get('laravel-dkim.dkim_domain'),
            $this->app['config']->get('laravel-dkim.dkim_selector'),
            $this->app['config']->get('laravel-dkim.dkim_passphrase')
        );

        $algo = $this->app['config']->get('laravel-dkim.dkim_algo', 'rsa-sha256');
        $signer->setHashAlgorithm(empty($algo) ? 'rsa-sha256' : $algo);
        if (! empty($identity = $this->app['config']->get('laravel-dkim.dkim_identity'))) {
            $signer->setSignerIdentity($identity);
        }

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
}
