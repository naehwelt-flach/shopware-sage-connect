## SageConnect

### install as git submodule

```
# composer.json
        
{
...
  "repositories": [
    {
      "type": "path",
      "url": "custom/plugins/*",
      "options": {
        "symlink": true
      }
    },
    ...
  ],
...
}
```

```
# custom/plugins/.gitignore

/*
!/NaehweltSageConnect/
...
```

```bash
# bash

git submodule add -b main \
  https://github.com/naehwelt-flach/shopware-sage-connect.git \
  custom/plugins/NaehweltSageConnect/

...

bin/console plugin:install SageConnect --activate
```


### configuration example

```php
# config/services.php

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