### config/services.php

```php
return static function(ContainerConfigurator $container): void {
    $services = $container->services()->defaults()->autowire()->autoconfigure();
    ...
    foreach ([
        'product_prices/' => 'sage_connect_product_prices',
        'product/' => 'sage_connect_product',
    ] as $prefix => $technicalName) {
        $services->set(ImportExport\DirectoryHandler::class . ".$prefix", ImportExport\DirectoryHandler::class)
            ->factory([service(ImportExport\DirectoryHandler::class), 'with'])
            ->args([
                '$location' => $prefix,
                '$profileCriteria' => ['technicalName' => $technicalName],
            ])
            ->tag(MessageQueue\EveryFiveMinutesHandler::class);
    }
    ...
}
```