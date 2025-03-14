<?php

declare(strict_types=1);

namespace Naehwelt\Shopware;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

class SageConnect extends Plugin
{
    public static function id(string $id = ''): string
    {
        return 'sage_connect' . $id;
    }

    public function activate(ActivateContext $activateContext): void
    {
        $this->installService($activateContext);
    }

    private function installService(InstallContext $context): void
    {
        $service = $this->container->get(InstallService::class);
        assert($service instanceof InstallService);
        $service->createImportExportProfileEntity($context->getContext());
        $service->importResources($context->getContext());
    }

    public function update(UpdateContext $updateContext): void
    {
        $this->installService($updateContext);
    }
}
