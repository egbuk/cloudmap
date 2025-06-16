<?php

namespace App\Entity;

use Imagick;
use ImagickException;
use ImagickPixelException;

readonly class Footage
{
    private Imagick $image;

    /**
     * @throws ImagickException
     */
    public function __construct(
        private string $url,
        private int $height,
        private int $width,
        private float $colorThreshold
    )
    {
        $this->image = new Imagick($this->url);
        $this->image->contrastImage(1);
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * @throws ImagickException
     * @throws ImagickPixelException
     */
    public function hasCloud(int $x, int $y): bool
    {
        $color = $this->image->getImagePixelColor($x, $y)->getColor(true);
        return array_sum($color) / count($color) > $this->colorThreshold;
    }
}
