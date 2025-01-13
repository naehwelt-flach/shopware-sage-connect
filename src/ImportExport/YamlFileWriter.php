<?php declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\ImportExport\Processing;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Symfony\Component\Yaml\Dumper;

class YamlFileWriter extends Processing\Writer\AbstractFileWriter
{
    private array $map = [];

    public function __construct(
        FilesystemOperator $filesystem,
        private readonly DefinitionInstanceRegistry $registry
    ){
        parent::__construct($filesystem);
    }

    public function finish(Config $config, string $targetPath): void
    {
        unset($this->map[spl_object_id($config)]);
        parent::finish($config, $targetPath);
    }

    private function createMap(Config $config): iterable
    {
        $definition = $this->registry->getByEntityName($config->get('sourceEntity'));
        $fields = $definition->getFields();
        foreach ($config->getMapping()->getElements() as $element) {
            if (($fields->get($element->getKey())) instanceof JsonField) {
                yield $element->getMappedKey() => fn ($json) => json_decode($json, true);
            }
        }
    }

    public function append(Config $config, array $data, int $index): void
    {
        $dumper = new Dumper(2);
        /* @var callable $fn */
        foreach ($this->map[spl_object_id($config)] ??= [...$this->createMap($config)] as $key => $fn) {
            ($value = $data[$key] ?? null) && $data[$key] = $fn($value);
        }
        $string = $dumper->dump([$data], 20);
        fwrite($this->buffer, ($index ? PHP_EOL : '') . $string);
    }
}
