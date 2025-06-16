<?php

namespace App\Service;

use App\Entity\Footage;
use ImagickException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class FootageService
{
    public function __construct(
        private string $url,
        private int $height,
        private int $width,
        private int $alphaThreshold,
        private string $tempDir,
        private Filesystem $filesystem,
        private HttpClientInterface $httpClient
    ) {}

    /**
     * @throws ImagickException
     */
    public function get(): Footage
    {
        return new Footage($this->load(), $this->height, $this->width,
            (float)$this->alphaThreshold / 255.0);
    }

    public function clear(Footage $footage): void
    {
        $this->filesystem->remove($footage->getUrl());
    }

    private function load(): string
    {
        $content = $this->httpClient->request('GET', $this->url)->getContent();
        $name = $this->filesystem->tempnam($this->tempDir, 'clouds', '.png');
        $this->filesystem->dumpFile($name, $content);
        return $name;
    }
}
