<?php

namespace App\Providers;

use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->afterResolving(MailManager::class, function (MailManager $manager) {
            $manager->extend('smtp', function (array $config) {
            $scheme = $config['scheme'] ?? null;

            if (! $scheme) {
                $scheme = ($config['port'] ?? null) == 465 ? 'smtps' : 'smtp';
            }

            $transport = (new EsmtpTransportFactory)->create(new Dsn(
                $scheme,
                $config['host'],
                $config['username'] ?? null,
                $config['password'] ?? null,
                $config['port'] ?? null,
                $config
            ));

            $stream = $transport->getStream();
            $caFile = $config['cafile'] ?? null;

            if ($stream instanceof SocketStream && is_string($caFile) && is_file($caFile)) {
                $streamOptions = $stream->getStreamOptions();
                $streamOptions['ssl']['cafile'] = $caFile;
                $stream->setStreamOptions($streamOptions);
            }

            if ($stream instanceof SocketStream && isset($config['timeout'])) {
                $stream->setTimeout($config['timeout']);
            }

            return $transport;
            });
        });
    }
}
