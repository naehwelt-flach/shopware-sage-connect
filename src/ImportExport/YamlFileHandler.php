<?php

declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport;

use Naehwelt\Shopware\DataAbstractionLayer\Provider;
use Shopware\Core\Content\ImportExport\Processing;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;

class YamlFileHandler extends AbstractFileHandler
{
    private const SERIALIZE = true;
    private const DESERIALIZE = false;
    private array $map = [];

    public function __construct(private readonly Provider $provider, private readonly Environment $twig)
    {
    }

    public function deserialize(Config $config, $resource, array $record, int $index): void
    {
        $record = $this->handle($config, $record, self::DESERIALIZE);
        $string = Yaml::dump([$record], 20, 2);
        fwrite($resource, ($index ? PHP_EOL : '') . $string);
    }

    private function handle(Config $config, array $data, bool $serialize): array
    {
        /* @var callable $fn */
        foreach ($this->map[spl_object_id($config)] ??= [...$this->createMap($config)] as $key => $fn) {
            ($value = $data[$key] ?? null) && $data[$key] = $fn($serialize, $value);
        }
        return $data;
    }

    private function createMap(Config $config): iterable
    {
        $definition = $this->provider->definition($config->get('sourceEntity'));
        $fields = $definition->getFields();
        foreach ($config->getMapping()->getElements() as $element) {
            if (($fields->get($element->getKey())) instanceof JsonField) {
                yield $element->getMappedKey() => self::json(...);
            }
        }
    }

    public function serialize(Config $config, $resource, int $offset): iterable
    {
        $records = Yaml::parse($this->getContent($config, $resource));
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

    private function getContent(Config $config, $resource): string
    {
        try {
            $loader = $this->twig->getLoader();
            $content = stream_get_contents($resource, offset: 0);
            $md5 = md5(self::json(true, [$config->jsonSerialize(), $content]));
            $name = sprintf('%s(%s) - %s', __METHOD__, $config->get('profileName'), $md5);
            $this->twig->setLoader(new ChainLoader([new ArrayLoader([$name => $content]), $loader]));
            return $this->twig->render($name);
        } finally {
            $this->twig->setLoader($loader);
        }
    }

    private static function json(bool $serialize, $value)
    {
        if ($serialize === self::SERIALIZE) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }
        /** @noinspection JsonEncodingApiUsageInspection */
        return json_decode($value, true);
    }
}
