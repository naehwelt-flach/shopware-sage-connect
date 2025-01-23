<?php

namespace Naehwelt\Shopware;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

class SageConnect extends Plugin
{
    public const PREFIX = 'sage_connect';

    public function activate(ActivateContext $activateContext): void
    {
        $this->generate($activateContext);
    }

    public function update(UpdateContext $updateContext): void
    {
        $this->generate($updateContext);
    }

    private function generate(InstallContext $context): void
    {
        $this->container?->get(DataService::class)?->generate($context->getContext());
    }
}
