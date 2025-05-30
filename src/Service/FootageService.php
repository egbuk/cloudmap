<?php

namespace App\Service;

use App\Entity\Footage;
use ImagickException;
use Symfony\Component\Filesystem\Filesystem;

readonly class FootageService
{
    public function __construct(
        private string $url,
        private int $height,
        private int $width,
        private int $alphaThreshold,
        private int $colorThreshold,
        private string $tempDir,
        private Filesystem $filesystem,
    ) {}

    /**
     * @throws ImagickException
     */
    public function get(): Footage
    {
        return new Footage($this->load(), $this->height, $this->width,
            (float)$this->alphaThreshold / 255.0,
            (float)$this->colorThreshold / 255.0);
    }

    public function clear(Footage $footage): void
    {
        $this->filesystem->remove($footage->getUrl());
    }

    private function load(): string
    {
        $content = $this->filesystem->readFile($this->url);
        $suffix = implode(array_slice(explode('.', $this->url), -1));
        $name = $this->filesystem->tempnam($this->tempDir, 'clouds', ".$suffix");
        $this->filesystem->dumpFile($name, $content);
        return $name;
    }
}
