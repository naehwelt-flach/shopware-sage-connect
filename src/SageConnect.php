<?php

declare(strict_types=1);

namespace Naehwelt\Shopware;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class SageConnect extends Plugin implements CompilerPassInterface
{
    public const ID = 'sage_connect';
    public const DIRECTORY_HANDLERS = self::ID . '.directory.handlers';
    public const ORDER_PLACED_PROFILE = self::ID . '.order.placed.profile';

    public static function id(string $id = ''): string
    {
        return self::ID . $id;
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

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass($this);
    }

    public function process(ContainerBuilder $container): void
    {
        $this->processDirectoryHandlers($container);
    }

    private function processDirectoryHandlers(ContainerBuilder $container): void
    {
        foreach ($container->getParameter(self::DIRECTORY_HANDLERS) ?? [] as $location => $config) {
            is_string($config) && $config = ['profile' => $config];
            $container->register(
                ImportExport\DirectoryHandler::class . ".$location",
                ImportExport\DirectoryHandler::class
            )->setFactory([new Reference(ImportExport\DirectoryHandler::class), 'with'])
                ->addArgument($location)
                ->addArgument($config['deleteAfterUpload'] ?? true)
                ->addArgument($config['criteria'] ?? ['profileCriteria' => ['technicalName' => $config['profile']]])
                ->addTag($config['period'] ?? MessageQueue\EveryFiveMinutesHandler::class);
        }
    }
}
