<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Naehwelt\Shopware\Checkout\PaymentProcessorDecorator;
use Naehwelt\Shopware\InstallService;
use Naehwelt\Shopware\Filesystem;
use Naehwelt\Shopware\ImportExport;
use Naehwelt\Shopware\DataAbstractionLayer;
use Naehwelt\Shopware\MessageQueue;
use Naehwelt\Shopware\SageConnect;
use Naehwelt\Shopware\Twig;
use ReflectionClass;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Payment\PaymentProcessor;
use Shopware\Core\Content\ImportExport\Controller\ImportExportActionController;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\FieldSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\PrimaryKeyResolver;
use Shopware\Core\Content\ImportExport\Event;
use Shopware\Core\Content\ImportExport\Service\FileService;
use Shopware\Core\Framework\Adapter\Filesystem\FilesystemFactory;
use Shopware\Core\Framework\Adapter\Filesystem\PrefixFilesystem;
use Shopware\Core\Framework\Api\Controller\SyncController;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

return static function(ContainerConfigurator $container, ContainerBuilder $builder): void {
    /* @see SageConnect::processDirectoryHandlers */
    $container->parameters()
        ->set(SageConnect::DIRECTORY_HANDLERS, ['product/' => 'sage_connect_product'])
        ->set(SageConnect::ORDER_PLACED_PROFILE, 'sage_connect_order_line_item')
    ;
    $container->extension('monolog', [
        'channels' => [SageConnect::ID],
        'handlers' => [
            SageConnect::ID => [
                'type' => 'filter',
                'handler' => SageConnect::id('_rotate'),
                'max_level' => Level::Warning->name,
                'channels' => [SageConnect::ID],
                ...($builder->getParameter('kernel.debug') ? [] : ['min_level' => Level::Info->name])
            ],
            SageConnect::id('_rotate') => [
                'type' => 'rotating_file',
                'filename_format' => '{date}-{filename}',
                'date_format' => RotatingFileHandler::FILE_PER_MONTH,
                'path' => '%kernel.logs_dir%/' . SageConnect::id('.log'),
            ],
        ]
    ]);

    $logger = service('monolog.logger.' . SageConnect::ID);

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
        foreach ((new ReflectionClass($handler))->getAttributes(AsMessageHandler::class) as $ref) {
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

        ->set(PrimaryKeyResolver::class, ImportExport\Serializer\PrimaryKeyResolver::class)
            ->args([
                service(DefinitionInstanceRegistry::class),
                service(FieldSerializer::class),
            ])
        ->alias(ImportExport\Serializer\PrimaryKeyResolver::class, PrimaryKeyResolver::class)

        ->set(ImportExport\Serializer\EntitySerializer::class)
            ->tag('shopware.import_export.entity_serializer', ['priority' => -900])

        ->set(ImportExport\Serializer\FieldSerializer::class)
            ->tag('shopware.import_export.field_serializer', ['priority' => -900])

        ->set(ImportExport\EventSubscriber::class)
            ->args([
                service(ImportExport\Serializer\PrimaryKeyResolver::class),
                tagged_iterator(Event\ImportExportBeforeImportRowEvent::class),
                tagged_iterator(ImportExport\Event\BeforeImportRecordEvent::class),
                tagged_iterator(Event\EnrichExportCriteriaEvent::class),
                $logger,
            ])

        ->set(ImportExport\Service\DefaultProductVisibilities::class)
            ->args([service(DataAbstractionLayer\Provider::class)])
            ->tag(ImportExport\Event\BeforeImportRecordEvent::class)

        ->set(ImportExport\Service\CalculateLinkedPrices::class)
            ->args([
                service(DataAbstractionLayer\Provider::class),
                service(NetPriceCalculator::class),
                service(GrossPriceCalculator::class),
            ])
            ->tag(ImportExport\Event\BeforeImportRecordEvent::class)

        ->set(ImportExport\Service\ProductVisibilityByStock::class)
            ->args([
                service('logger'),
                service('product.repository'),
            ])
        ->tag(ImportExport\Event\BeforeImportRecordEvent::class)

        ->set(ImportExport\Service\EnrichCriteria::class)
            ->args([
                inline_service(ImportExport\ProcessFactory::class)
                    ->factory([service(ImportExport\ProcessFactory::class), 'with'])
                    ->args([
                        ['technicalName' => param(SageConnect::ORDER_PLACED_PROFILE)],
                        '+1 month'
                    ]),
                service(FileService::class),
            ])
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

        ->set(SageConnect::FS_TEMP, PrefixFilesystem::class)
            ->args([
                service('shopware.filesystem.temp'),
                'plugins/' . SageConnect::ID
            ])

        ->set(Filesystem\MountManager::class)->public()
            ->args([
                service(SageConnect::FS_PRIVATE),
                service(SageConnect::FS_TEMP),
                service('mime_types'),
            ])

        ->set(ImportExport\ProcessFactory::class)
            ->args([
                service(DataAbstractionLayer\Provider::class),
                service(ImportExportActionController::class),
                [],
                $logger,
            ])

        ->set(ImportExport\DirectoryHandler::class)
            ->args([
                service(Filesystem\MountManager::class),
                service(ImportExport\ProcessFactory::class),
            ])

        ->set(SageConnect::FS_RESOURCES, PrefixFilesystem::class)
            ->factory([service(FilesystemFactory::class), 'privateFactory'])
            ->args([[
                'type' => 'local',
                'config' => ['root' => dirname(__DIR__)]
            ]])

        ->set(PaymentProcessorDecorator::class)
            ->decorate(PaymentProcessor::class)
            ->args([
                service('.inner'),
                service(DataAbstractionLayer\Provider::class),
                service(ImportExport\Service\EnrichCriteria::class),
                $logger,
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
                                service(SageConnect::FS_RESOURCES),
                            ]),
                        '$location' => ''
                    ])
            ])

    ;
};