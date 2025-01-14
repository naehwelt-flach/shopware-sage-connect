<?php declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport;

use Shopware\Core\Content\ImportExport\Processing;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Symfony\Component\Yaml\Yaml;

class YamlFileHandler extends AbstractFileHandler
{
    private array $map = [];

    private const SERIALIZE = true;
    private const DESERIALIZE = false;

    public function __construct(private readonly DefinitionInstanceRegistry $registry){}

    private function createMap(Config $config): iterable
    {
        $definition = $this->registry->getByEntityName($config->get('sourceEntity'));
        $fields = $definition->getFields();
        foreach ($config->getMapping()->getElements() as $element) {
            if (($fields->get($element->getKey())) instanceof JsonField) {
                yield $element->getMappedKey() => $this->jsonField(...);
            }
        }
    }

    private function handle(Config $config, array $data, bool $serialize): array
    {
        /* @var callable $fn */
        foreach ($this->map[spl_object_id($config)] ??= [...$this->createMap($config)] as $key => $fn) {
            ($value = $data[$key] ?? null) && $data[$key] = $fn($serialize, $value);
        }
        return $data;
    }

    private function jsonField(bool $serialize, $value)
    {
        if ($serialize === self::SERIALIZE) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }
        return json_decode($value, true);
    }

    public function deserialize(Config $config, $resource, array $record, int $index): void
    {
        $record = $this->handle($config, $record, self::DESERIALIZE);
        $string = Yaml::dump([$record], 20, 2);
        fwrite($resource, ($index ? PHP_EOL : '') . $string);
    }

    public function serialize(Config $config, $resource, int $offset): iterable
    {
        $records = Yaml::parse(stream_get_contents($resource, offset: 0));
        if ($last = array_pop($records)) {
            $stat = fstat($resource);
            $records[$stat['size']] = $last;
        }
        foreach ($records as $position => $record) {
            if ($offset > $position) {
                continue;
            }
            $record = $this->handle($config, $record, self::SERIALIZE);
            yield $position => $record;
        }
    }
}
