<?php
namespace Symfony\Component\DependencyInjection\Loader\Configurator;
use Naehwelt\Shopware\ImportExport;

return static function(ContainerConfigurator $container): void {
    $container->services()->defaults()->autowire()->autoconfigure()
        ->set(ImportExport\Task::class)
            ->tag('shopware.scheduled.task')

        ->set(ImportExport\TaskHandler::class)
            ->args([
                '@scheduled_task.repository',
                '@monolog.logger',
            ])
            ->tag('messenger.message_handler')
    ;
};