## Laravel DKIM (tobymaxham/laravel-dkim)

This package adds DKIM signatures to outgoing Laravel mails. It listens to `Illuminate\Mail\Events\MessageSending` and does not replace the mailer.

- Never register the service provider by hand. It is auto-discovered.
- Signing is off by default. Enable it with `MAIL_DKIM_ENABLED=true`.
- Create keys with `php artisan dkim:generate example.com`. Check key and DNS with `php artisan dkim:check`.
- Configure senders in `config/laravel-dkim.php` under `sender`. A sender is chosen by the domain of the From address (`dkim_domain`), or by exact address (`addresses`). Never choose it by `APP_URL`.
- Never print, log or commit private keys or passphrases. Keep key files outside `public/` with mode 0600.
- Do not change `dkim_selector` or the key of an existing sender without asking: the DNS record must match, otherwise signatures fail.
- Do not sign mails that a provider signs for the same domain already.
- For setup use the skill `laravel-dkim-setup`, for upgrades between major versions the skill `laravel-dkim-upgrade`.
