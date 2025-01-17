<?php
namespace Symfony\Component\DependencyInjection\Loader\Configurator;
use Naehwelt\Shopware\DataService;
use Naehwelt\Shopware\PriceService;
use Naehwelt\Shopware\ImportExport;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Shopware\Core\Content\ImportExport\Service\FileService;
use Shopware\Core\Framework\Api\Controller\SyncController;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;

return static function(ContainerConfigurator $container): void {
    $services = $container->services()->defaults()->autowire()->autoconfigure();

    $set = function ($id, $class = null, array $args = []) use ($services) : string|ServiceConfigurator {
        if (is_array($class)) {
            return $services->set($id)->args($class);
        }
        if ($class === null) {
            return $services->set($id)->args($args);
        }

        $services->set($id, $class)->args($args);
        return $id;
    };

    $services
        ->set(DataService::class)->public()
            ->args([
                service(SyncController::class),
                service('request_stack'),
            ])

        ->set(PriceService::class)
            ->args([
                service(DefinitionInstanceRegistry::class),
                service(NetPriceCalculator::class),
                service(GrossPriceCalculator::class),
            ])
            ->tag('kernel.event_listener', [
                'event' => ImportExportBeforeImportRecordEvent::class,
                'method' => [PriceService::class, 'calculateLinkedPrice'][1]
            ])

        ->set(ImportExport\Task::class)
            ->tag('shopware.scheduled.task')

        ->set(ImportExport\TaskHandler::class)
            ->args([
                service('scheduled_task.repository'),
                service('monolog.logger'),
            ])
            ->tag('messenger.message_handler')

        ->set(FileService::class)
            ->class(ImportExport\FileService::class)
            ->args([
                service('shopware.filesystem.private'),
                service('import_export_file.repository'),
            ])

        ->set(ImportExport\YamlFileHandler::class)
            ->args([service(DefinitionInstanceRegistry::class)])

        ->set(ImportExport\WriterFactory::class)
            ->args([
                ['application/x-yaml', 'text/yaml'],
                service_closure($set('sc.yaml.writer', ImportExport\FileWriter::class, [
                    service('shopware.filesystem.private'),
                    service(ImportExport\YamlFileHandler::class)
                ]))
            ])
            ->tag('shopware.import_export.writer_factory')

        ->set(ImportExport\ReaderFactory::class)
            ->args([
                ['application/x-yaml', 'text/yaml'],
                service_closure($set('sc.yaml.reader', ImportExport\FileReader::class, [
                    service(ImportExport\YamlFileHandler::class)
                ]))
            ])
            ->tag('shopware.import_export.reader_factory')

    ;
};