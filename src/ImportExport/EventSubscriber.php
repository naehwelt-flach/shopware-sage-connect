<?php

declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport;

use Naehwelt\Shopware\SageConnect;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use Shopware\Core\Content\ImportExport\Event\EnrichExportCriteriaEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRowEvent;
use Shopware\Core\Content\ImportExport\Processing;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class EventSubscriber implements EventSubscriberInterface
{
    private iterable $services;

    public function __construct(
        iterable $beforeImportRowServices,
        iterable $beforeImportRecordServices,
        iterable $enrichExportCriteriaServices,
        private array $namespaces = [
            __NAMESPACE__ . '\\Service\\' => ''
        ]
    ) {
        $this->services = [
            ImportExportBeforeImportRowEvent::class => [...$this->mapNamespaces($beforeImportRowServices)],
            ImportExportBeforeImportRecordEvent::class => [...$this->mapNamespaces($beforeImportRecordServices)],
            EnrichExportCriteriaEvent::class => [...$this->mapNamespaces($enrichExportCriteriaServices)],
        ];
    }

    private function mapNamespaces(iterable $services): iterable
    {
        foreach ($services as $service) {
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

    public static function params(string|array|callable $target, array $params): array
    {
        $class = self::refParams($target)[0]->getType()?->getName();
        return [SageConnect::id() => [self::getSubscribedEvents()[$class] => $params]];
    }

    /**
     * @return ReflectionParameter[]
     */
    private static function refParams(string|array|callable $target): array
    {
        is_string($target) && new ReflectionMethod($target, '__invoke');
        if (is_string($target)) {
            $target = explode('::', $target, 2) + [1 => '__invoke'];
        }
        if (is_array($target) && method_exists(...$target)) {
            return (new ReflectionMethod(...$target))->getParameters();
        }
        return (new ReflectionFunction($target(...)))->getParameters();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ImportExportBeforeImportRowEvent::class => 'onBeforeImportRow',
            ImportExportBeforeImportRecordEvent::class => 'onBeforeImportRecord',
            EnrichExportCriteriaEvent::class => 'onEnrichExportCriteria',
        ];
    }

    public function onBeforeImportRow(ImportExportBeforeImportRowEvent $event): void
    {
        $this->onEvent($event, __FUNCTION__);
    }

    private function onEvent(
        ImportExportBeforeImportRowEvent|ImportExportBeforeImportRecordEvent|EnrichExportCriteriaEvent $event,
        string $key,
        Config $config = null
    ): void {
        $config ??= $event->getConfig();
        $configuredServices = (array)($config->get(SageConnect::id())[$key] ?? null);
        foreach ($configuredServices as $name => $params) {
            $cl = $event::class;
            $service = $this->services[$cl][$name] ?? null;
            if ($service) {
                $service($event, (array)$params);
            }
        }
    }

    public function onBeforeImportRecord(ImportExportBeforeImportRecordEvent $event): void
    {
        $this->onEvent($event, __FUNCTION__);
    }

    public function onEnrichExportCriteria(EnrichExportCriteriaEvent $event): void
    {
        $this->onEvent($event, __FUNCTION__, Config::fromLog($event->getLogEntity()));
    }
}
