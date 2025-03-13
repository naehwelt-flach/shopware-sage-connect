<?php
namespace Symfony\Component\DependencyInjection\Loader\Configurator;
use Naehwelt\Shopware\InstallService;
use Naehwelt\Shopware\Filesystem;
use Naehwelt\Shopware\ImportExport;
use Naehwelt\Shopware\DataAbstractionLayer;
use Naehwelt\Shopware\MessageQueue;
use Naehwelt\Shopware\SageConnect;
use Naehwelt\Shopware\Twig;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Content\ImportExport\Controller\ImportExportActionController;
use Shopware\Core\Content\ImportExport\Event;
use Shopware\Core\Content\ImportExport\Service\FileService;
use Shopware\Core\Framework\Adapter\Filesystem\FilesystemFactory;
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

        ->set(ImportExport\Serializer\EntitySerializer::class)
            ->tag('shopware.import_export.entity_serializer', ['priority' => -900])

        ->set(ImportExport\EventSubscriber::class)
            ->args([
                tagged_iterator(Event\ImportExportBeforeImportRowEvent::class),
                tagged_iterator(Event\ImportExportBeforeImportRecordEvent::class),
                tagged_iterator(Event\EnrichExportCriteriaEvent::class),
            ])
            ->tag('kernel.event_subscriber')

        ->set(ImportExport\Service\DefaultProductVisibilities::class)
            ->args([service(DataAbstractionLayer\Provider::class)])
            ->tag(Event\ImportExportBeforeImportRowEvent::class)

        ->set(ImportExport\Service\CalculateLinkedPrices::class)
            ->args([
                service(DataAbstractionLayer\Provider::class),
                service(NetPriceCalculator::class),
                service(GrossPriceCalculator::class),
            ])
            ->tag(Event\ImportExportBeforeImportRecordEvent::class)

        ->set(ImportExport\Service\EnrichCriteria::class)
            ->tag(Event\EnrichExportCriteriaEvent::class)

        ->set(FileService::class)
            ->class(ImportExport\FileService::class)
            ->args([
                service('shopware.filesystem.private'),
                service('import_export_file.repository'),
            ])

        ->set(Twig\Extension::class)
            ->args([service(DataAbstractionLayer\Provider::class)])
            ->tag('twig.extension')

        ->set(ImportExport\YamlFileHandler::class)
            ->args([service(DataAbstractionLayer\Provider::class), service('twig')])

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

        ->set(SageConnect::id('.filesystem.temp'), PrefixFilesystem::class)
            ->args([
                service('shopware.filesystem.temp'),
                'plugins/' . SageConnect::id()
            ])

        ->set(Filesystem\MountManager::class)->public()
            ->args([
                service(SageConnect::id('.filesystem.private')),
                service(SageConnect::id('.filesystem.temp')),
                service('mime_types'),
            ])

        ->set(ImportExport\ProcessFactory::class)
            ->args([
                service(DataAbstractionLayer\Provider::class),
                service(ImportExportActionController::class),
                [],
            ])

        ->set(ImportExport\DirectoryHandler::class)
            ->args([
                service(Filesystem\MountManager::class),
                service(ImportExport\ProcessFactory::class),
            ])

        ->set(SageConnect::id('.filesystem.resources'), PrefixFilesystem::class)
            ->factory([service(FilesystemFactory::class), 'privateFactory'])
            ->args([[
                'type' => 'local',
                'config' => ['root' => dirname(__DIR__)]
            ]])

        ->set(ImportExport\Event\OrderPlacedListener::class)
            ->args([
                inline_service(ImportExport\ProcessFactory::class)
                    ->factory([service(ImportExport\ProcessFactory::class), 'with'])
                    ->args([['technicalName' => 'default_orders']]) // todo
            ])

        ->set(InstallService::class)->public()
            ->args([
                service(SyncController::class),
                service('request_stack'),
                inline_service(ImportExport\DirectoryHandler::class)
                    ->factory([service(ImportExport\DirectoryHandler::class), 'with'])
                    ->args([
                        '$mountManager' => inline_service(Filesystem\MountManager::class)
                            ->factory([service(Filesystem\MountManager::class), 'with'])
                            ->args([
                                service(SageConnect::id('.filesystem.resources')),
                            ]),
                        '$location' => ''
                    ])
            ])

    ;
};