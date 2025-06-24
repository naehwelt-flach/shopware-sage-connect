<?php

declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport;

use Naehwelt\Shopware\ImportExport\Serializer\PrimaryKeyResolver;
use Naehwelt\Shopware\SageConnect;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\ImportExport\Event\EnrichExportCriteriaEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRowEvent;
use Shopware\Core\Content\ImportExport\Processing;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

class EventSubscriber implements ResetInterface
{
    public const ON_BEFORE_IMPORT_ROW = 'onBeforeImportRow';
    public const ON_BEFORE_IMPORT_RECORD = 'onBeforeImportRecord';
    public const ON_ENRICH_EXPORT_CRITERIA = 'onEnrichExportCriteria';

    private array $cache;

    public function __construct(
        private readonly PrimaryKeyResolver $primaryKeyResolver,
        private readonly array $services,
        private readonly LoggerInterface $logger,
        private readonly array $namespaces = [__NAMESPACE__ . '\\Service\\' => '']
    ) {
    }

    private function mapNamespaces(string $tag): iterable
    {
        foreach ($this->services[$tag] ?? [] as $service) {
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

    private function services(Config|array $config, string $tag): iterable
    {
        $config instanceof Config && $config = $config->get(SageConnect::ID);
        foreach ((array)($config[$tag] ?? null) as $name => $params) {
            $this->cache[$tag] ??= [...$this->mapNamespaces($tag)];
            $service = $this->cache[$tag][$name] ?? null;
            if ($service) {
                yield $service => (array)$params;
            } else {
                $this->logger->info("Service '$tag/$name' not found");
            }
        }
    }

    private function tryAndCatch(callable $callback, ...$args): void
    {
        try {
            $callback(...$args);
        } catch (Throwable $error) {
            $this->logger->error($error->getMessage(), ['e' => $error]);
        }
    }

    public static function params(string $on, string $service, array $params): array
    {
        return [SageConnect::id() => [$on => [$service => $params]]];
    }

    #[AsEventListener]
    public function onBeforeImportRow(ImportExportBeforeImportRowEvent $event): void
    {
        foreach ($this->services($event->getConfig(), self::ON_BEFORE_IMPORT_ROW) as $service => $params) {
            $this->tryAndCatch(fn() => $service($event, $params));
        }
    }

    #[AsEventListener]
    public function onBeforeImportRecord(ImportExportBeforeImportRecordEvent $event): void
    {
        $config = $event->getConfig();
        $record = $event->getRecord();
        foreach ($this->services($config, self::ON_BEFORE_IMPORT_RECORD) as $service => $params) {
            $this->tryAndCatch(fn() => $service($event, $this->primaryKeyResolver->resolved($config, $record), $params));
        }
    }

    #[AsEventListener]
    public function onEnrichExportCriteria(EnrichExportCriteriaEvent $event): void
    {
        foreach ($this->services(Config::fromLog($event->getLogEntity()), self::ON_ENRICH_EXPORT_CRITERIA) as $service => $params) {
            $this->tryAndCatch(fn() => $service($event, $params));
        }
    }

    public function reset(): void
    {
        $this->cache = [];
    }
}
