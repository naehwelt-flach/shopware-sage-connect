### config/services.php

```php
return static function(ContainerConfigurator $container): void {
    $services = $container->services()->defaults()->autowire()->autoconfigure();
    ...
    foreach ([
        'foo/' => 'sc_product',
        'bar/' => 'sc_product',
    ] as $prefix => $technicalName) {
        $services->set(ImportExport\DirectoryHandler::class. $prefix, ImportExport\DirectoryHandler::class)
            ->args([
                service(DataAbstractionLayer\Provider::class),
                service(ImportExportActionController::class),
                inline_service(PrefixFilesystem::class)
                    ->args([service('shopware.filesystem.temp'), $prefix]),
                ['technicalName' => $technicalName],
                '$logger' => service('logger'),
            ])
            ->tag(MessageQueue\EveryFiveMinutesHandler::class);
    }
    ...
}

```