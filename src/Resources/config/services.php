<?php
namespace Symfony\Component\DependencyInjection\Loader\Configurator;
use Naehwelt\Shopware\DataService;
use Naehwelt\Shopware\ImportExport;
use Shopware\Core\Framework\Api\Controller\SyncController;

return static function(ContainerConfigurator $container): void {
    $container->services()->defaults()->autowire()->autoconfigure()
        ->set(ImportExport\Task::class)
            ->tag('shopware.scheduled.task')

        ->set(ImportExport\TaskHandler::class)
            ->args([
                service('scheduled_task.repository'),
                service('monolog.logger'),
            ])
            ->tag('messenger.message_handler')

        ->set(DataService::class)
            ->args([
                service(SyncController::class),
                service('request_stack'),
            ])
            ->public()
    ;
};