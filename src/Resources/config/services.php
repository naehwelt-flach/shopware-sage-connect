<?php
namespace Symfony\Component\DependencyInjection\Loader\Configurator;
use Naehwelt\Shopware\DataService;
use Naehwelt\Shopware\ImportExport;
use Shopware\Core\Content\ImportExport\Service\FileService;
use Shopware\Core\Framework\Api\Controller\SyncController;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;

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

        ->set(DataService::class)->public()
            ->args([
                service(SyncController::class),
                service('request_stack'),
            ])

        ->set(FileService::class)
            ->class(ImportExport\FileService::class)
            ->args([
                service('shopware.filesystem.private'),
                service('import_export_file.repository'),
            ])

        ->set(ImportExport\YamlFileWriter::class)
            ->args([
                service('shopware.filesystem.private'),
                service(DefinitionInstanceRegistry::class),
            ])
        ->set('sc.writer_factory.yaml', ImportExport\WriterFactory::class)
            ->args(['text/yaml', service_closure(ImportExport\YamlFileWriter::class)])
            ->tag('shopware.import_export.writer_factory')

        ->set(ImportExport\YamlReader::class)
        ->set('sc.reader_factory.yaml', ImportExport\ReaderFactory::class)
            ->args(['text/yaml', service_closure(ImportExport\YamlReader::class)])
            ->tag('shopware.import_export.reader_factory')

    ;
};