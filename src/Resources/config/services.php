<?php
namespace Symfony\Component\DependencyInjection\Loader\Configurator;
use Naehwelt\Shopware\DataService;
use Naehwelt\Shopware\Filesystem;
use Naehwelt\Shopware\PriceService;
use Naehwelt\Shopware\ImportExport;
use Naehwelt\Shopware\DataAbstractionLayer;
use Naehwelt\Shopware\MessageQueue;
use Naehwelt\Shopware\SageConnect;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Content\ImportExport\Controller\ImportExportActionController;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Shopware\Core\Content\ImportExport\ImportExportFactory;
use Shopware\Core\Content\ImportExport\Service\FileService;
use Shopware\Core\Framework\Adapter\Filesystem\PrefixFilesystem;
use Shopware\Core\Framework\Api\Controller\SyncController;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

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

    foreach ([
        MessageQueue\EveryMinuteHandler::class,
        MessageQueue\EveryFiveMinutesHandler::class,
        MessageQueue\EveryHourHandler::class,
    ] as $handler) {
        foreach ((new \ReflectionClass($handler))->getAttributes(AsMessageHandler::class) as $ref) {
            /** @var AsMessageHandler $attr */
            if (($attr = $ref->newInstance())->handles) {
                $services
                    ->set($handler)
                        ->args([
                            service('scheduled_task.repository'),
                            service('logger'),
                            tagged_iterator($handler),
                        ])
                    ->set($attr->handles)->tag('shopware.scheduled.task');
                break;
            }
        }
    }

    $services
        ->set(DataAbstractionLayer\Provider::class)
            ->args([
                service(DefinitionInstanceRegistry::class),
                service(RequestCriteriaBuilder::class),
                inline_service(Context::class)->factory([Context::class, 'createDefaultContext'])
            ])

        ->set(DataService::class)->public()
            ->args([
                service(SyncController::class),
                service('request_stack'),
            ])

        ->set(PriceService::class)
            ->args([
                service(DataAbstractionLayer\Provider::class),
                service(NetPriceCalculator::class),
                service(GrossPriceCalculator::class),
            ])
            ->tag('kernel.event_listener', [
                'event' => ImportExportBeforeImportRecordEvent::class,
                'method' => [PriceService::class, 'calculateLinkedPrice'][1]
            ])

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

        ->set(SageConnect::PREFIX . '.filesystem.temp', PrefixFilesystem::class)
            ->args([
                service('shopware.filesystem.temp'),
                'plugins/' . SageConnect::PREFIX
            ])

        ->set(Filesystem\MountManager::class)->public()
            ->args([
                service(SageConnect::PREFIX . '.filesystem.private'),
                service(SageConnect::PREFIX . '.filesystem.temp'),
                service('mime_types'),
            ])

        ->set(ImportExport\DirectoryHandler::class)
            ->args([
                service(Filesystem\MountManager::class),
                service(DataAbstractionLayer\Provider::class),
                service(DataAbstractionLayer\Provider::class),
                service(ImportExportFactory::class),
                service(ImportExportActionController::class),
                [],
                []
            ])
    ;
};