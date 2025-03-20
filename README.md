## SageConnect

### install as git submodule

```
# composer.json
        
{
...
  "repositories": [
    {
      "type": "path",
      "url": "custom/static-plugins/*",
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
# custom/static-plugins/.gitignore

/*
!/NaehweltSageConnect/
...
```

```bash

# Add git submodule to your project
git submodule add -b main \
  https://github.com/naehwelt-flach/shopware-sage-connect.git \
  custom/static-plugins/NaehweltSageConnect/

# The installation process DOES NOT automatically import `src/Resources/profiles/*.yaml`.
# It ONLY creates import messages in the queue (e.g., the `log_entry` table). 
# To complete the import, an asynchronous message receiver must be set up in advance.
# More information: https://docs.shopware.com/en/shopware-6-en/tutorials-and-faq/message-queue-and-scheduled-tasks

# As a workaround, run a limited asynchronous consumer as a background process:  
bin/console messenger:consume async --limit 1000 &

# Install and activate the plugin
bin/console plugin:install SageConnect --activate

# Alternatively, you can explicitly import profiles using the following command:
bin/console import:entity --printErrors \ 
  --profile-technical-name=sage_connect_import_export_profile_yaml \
  custom/plugins/NaehweltSageConnect/src/Resources/profiles/product.yaml now 
```


### directory handler configuration example

```php
# config/services.php

return static function(ContainerConfigurator $container): void {
    ...
    $container->parameters()->set(SageConnect::DIRECTORY_HANDLERS, [
        'product/' => 'sage_connect_product',
        'product-hourly-persistent/' => [
            'profile' => 'sage_connect_product',
            'deleteAfterUpload' => false,
            'period' => MessageQueue\EveryHourHandler::class
        ],
    ]);
    ...
}
```