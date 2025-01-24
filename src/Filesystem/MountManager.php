<?php declare(strict_types=1);

namespace Naehwelt\Shopware\Filesystem;

use League\Flysystem;
use Shopware\Core\Defaults;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Mime\MimeTypesInterface;

readonly class MountManager
{
    private Flysystem\MountManager $mm;

    public function __construct(
        Flysystem\FilesystemOperator $sourceFs,
        private Flysystem\FilesystemOperator $tempFs,
        private MimeTypesInterface $mimeTypes,
        private string $location = '',
    ) {
        $this->mm = new Flysystem\MountManager(['source' => $sourceFs, 'tmp' => $this->tempFs]);
    }

    public function with(
        null|Flysystem\FilesystemOperator $sourceFs = null,
        null|string $location = null,
    ): static {
        return new static(
            $sourceFs ?? $this->mm,
            $this->tempFs,
            $this->mimeTypes,
            $location ?? $this->location,
        );
    }

    /**
     * @return iterable<array{path: string, originalName: string, mimeType: string}, Flysystem\FileAttributes>
     */
    public function files(string|array $type, string $location = '', bool $copy = false): iterable
    {
        $types = array_unique([...$this->types((array)$type)]);
        $location = Path::join($this->location, $location);
        foreach ($this->mm->listContents("source://$location") as $file) {
            if ($file->isDir()) {
                continue;
            }
            $originalName = $path = $file->path();
            $ext = pathinfo($originalName, PATHINFO_EXTENSION);
            if (!array_intersect($types, $this->mimeTypes->getMimeTypes($ext))) {
                continue;
            }
            if ($copy) {
                $temp ??= 'tmp://' . self::ts();
                $path = explode('://', $originalName, 2)[1];
                $this->mm->copy($originalName, $path = "$temp-$path");
            }
            $path = (string)stream_get_meta_data($this->mm->readStream($path))['uri'];
            yield ['path' => $path, 'originalName' => $originalName, 'mimeType' => $types[0]] => $file;
        }
    }

    private function types(array $types): iterable
    {
        foreach ($types as $type) {
            if (($ext = explode('.', $type))[0] === '') {
                yield from $this->mimeTypes->getMimeTypes($ext[1] ?? '');
            } else {
                yield $type;
            }
        }
    }

    private static function ts(): string
    {
        return str_replace(' ', 'T', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT));
    }
}
