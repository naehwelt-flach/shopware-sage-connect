<?php
declare(strict_types=1);

namespace Naehwelt\Shopware\Twig;

use Naehwelt\Shopware\DataAbstractionLayer\Provider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class Extension extends AbstractExtension
{
    public function __construct(readonly Provider $provider) {}

    public function getFilters(): iterable
    {
        yield new TwigFilter('entity_*', function (string $class, $id) {
            return $this->provider->entity($class, $id);
        });
    }
}
