<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use App\Bundle\Message\DownloadImage;
use App\Bundle\Message\SaveRecord;
use Symfony\Config\FrameworkConfig;

return static function (FrameworkConfig $framework): void {
    $messenger = $framework->messenger();

    $messenger->failureTransport('failed')
        ->transport('async', env('MESSENGER_TRANSPORT_DSN'))
        ->transport('failed', env('MESSENGER_TRANSPORT_FAILED_DSN'))
        ->transport('sync', 'sync://');

    $messenger->routing(SaveRecord::class, 'async');
    $messenger->routing(DownloadImage::class, 'async');
};