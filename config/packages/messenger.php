<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use App\Bundle\Message\DownloadImage;
use App\Bundle\Message\SaveRecord;
use Symfony\Config\FrameworkConfig;

return static function (FrameworkConfig $framework): void {
    $messenger = $framework->messenger();

    $messenger->failureTransport('failed');
    $messenger->transport('async')->dsn(env('MESSENGER_TRANSPORT_DSN'));
    $messenger->transport('failed')->dsn(env('MESSENGER_TRANSPORT_FAILED_DSN'));
    $messenger->transport('sync')->dsn('sync://');

    $messenger->routing(SaveRecord::class)->senders(['async']);
    $messenger->routing(DownloadImage::class)->senders(['async']);
};
