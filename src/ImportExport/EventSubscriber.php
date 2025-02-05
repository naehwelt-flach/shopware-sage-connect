<?php declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport;

use Naehwelt\Shopware\SageConnect;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRowEvent;
use Shopware\Core\Content\ImportExport\Processing;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class EventSubscriber implements EventSubscriberInterface
{
    private iterable $services;

    public function __construct(
        iterable $beforeImportRowServices,
        iterable $beforeImportRecordServices,
        private array $namespaces = [
            __NAMESPACE__ . '\\Service\\' => ''
        ]
    ) {
        $this->services = [
            ImportExportBeforeImportRowEvent::class => [...$this->mapNamespaces($beforeImportRowServices)],
            ImportExportBeforeImportRecordEvent::class => [...$this->mapNamespaces($beforeImportRecordServices)],
        ];
    }

    private function mapNamespaces(iterable $services): iterable
    {
        foreach ($services as $key => $service) {
            $class = $service::class;
            yield $class => $service;
            foreach ($this->namespaces as $ns => $alias) {
                if (str_starts_with($class, $ns)) {
                    yield $alias . explode($ns, $class, 2)[1] => $service;
                    break;
                }
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ImportExportBeforeImportRowEvent::class => 'onBeforeImportRow',
            ImportExportBeforeImportRecordEvent::class => 'onBeforeImportRecord',
        ];
    }

    public function onBeforeImportRow(ImportExportBeforeImportRowEvent $event): void
    {
        $this->onEvent($event, __FUNCTION__);
    }

    public function onBeforeImportRecord(ImportExportBeforeImportRecordEvent $event): void
    {
        $this->onEvent($event, __FUNCTION__);
    }

    private function onEvent(ImportExportBeforeImportRowEvent|ImportExportBeforeImportRecordEvent $event, string $key): void
    {
        $configuredServices = (array)($event->getConfig()->get(SageConnect::id())[$key] ?? null);
        foreach ($configuredServices as $name => $config) {
            $cl = $event::class;
            $service = $this->services[$cl][$name] ?? null;
            if ($service) {
                $service($event, (array)$config);
            }
        }
    }
}
