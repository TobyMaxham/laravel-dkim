<?php

namespace TobyMaxham\LaravelDkimSigner;

use Illuminate\Support\Arr;
use Symfony\Component\Mime\Crypto\DkimOptions;

class DkimSigner
{
    protected \Illuminate\Contracts\Foundation\Application $app;
    protected string $configSender = 'default';

    private array $requiredConfig = [
        'dkim_selector',
        'dkim_domain',
        'dkim_private_key',
    ];

    private array $use_config = [];

    public function __construct(\Illuminate\Contracts\Foundation\Application $app)
    {
        $this->app = $app;
    }

    public function signMessage(\Symfony\Component\Mime\Message $message, ?string $sender)
    {
        if (empty($sender)) {
            $sender = $this->app['config']->get('laravel-dkim.dkim_default_sender');
        }

        if (! $this->app['config']->get('laravel-dkim.dkim_signer_enabled')) {
            return $message;
        }

        $this->configSender = str_replace('.', '_', $sender);

        $signer = $this->getSigner();
        if ($signer instanceof \Symfony\Component\Mime\Crypto\DkimSigner) {
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
            ! is_file($keyfile = Arr::get($this->use_config, 'dkim_private_key')),
            'Invalid file: '.$keyfile
        );

        $signer = new \Symfony\Component\Mime\Crypto\DkimSigner(
            'file://'.$keyfile,
            Arr::get($this->use_config, 'dkim_domain'),
            Arr::get($this->use_config, 'dkim_selector'),
            [],
            Arr::get($this->use_config, 'dkim_passphrase') ?? ''
        );

        return $signer;
    }

    private function hasRequiredConfig(): bool
    {
        $config = $this->app['config']->get('laravel-dkim.sender.'.$this->configSender, []);
        if (empty($config)) {
            $config = $this->app['config']->get('laravel-dkim.sender.'.$this->app['config']->get('laravel-dkim.dkim_default_sender'), []);
        }

        $bool = count($this->requiredConfig) === collect($config)
                ->only($this->requiredConfig)
                ->filter()
                ->count();

        if (! $bool) {
            return false;
        }

        $this->useConfig($config);

        return true;
    }

    private function useConfig(array $config): void
    {
        $this->use_config = $config;
    }

    private function getOptions(): DkimOptions
    {
        $options = new DkimOptions();

        $algo = Arr::get($this->use_config, 'dkim_algo', 'rsa-sha256');
        if (empty($algo)) {
            $algo = 'rsa-sha256';
        }

        $options->algorithm($algo);

        return $options;
    }
}
