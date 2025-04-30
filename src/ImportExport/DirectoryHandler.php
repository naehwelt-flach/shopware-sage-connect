<?php

declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport;

use Naehwelt\Shopware\Filesystem\MountManager;
use Shopware\Core\Framework\Context;

readonly class DirectoryHandler
{
    public function __construct(
        public MountManager $mountManager,
        public ProcessFactory $processFactory,
        private string $location = '',
        private bool $deleteAfterUpload = false,
    ) {
    }

    public function with(
        null|string $location = null,
        null|bool $deleteAfterUpload = null,
        null|array|ProcessFactory $processFactory = null,
        null|MountManager $mountManager = null,
    ): static {
        is_array($processFactory) && $processFactory = $this->processFactory->with(...$processFactory);
        return new static(
            $mountManager ?? $this->mountManager,
            $processFactory ?? $this->processFactory,
            $location ?? $this->location,
            $deleteAfterUpload ?? $this->deleteAfterUpload,
        );
    }

    public function __invoke(Context $context = null): void
    {
        $fileType = $this->processFactory->profile()->getFileType();
        $copy = !$this->deleteAfterUpload;
        foreach ($this->mountManager->files($fileType, $this->location, copy: $copy) as $import => $_) {
            $this->processFactory->sendMessage($import, context: $context);
        }
    }
}
