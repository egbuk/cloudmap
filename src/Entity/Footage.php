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
        private float $alphaThreshold
    )
    {
        $this->image = new Imagick($this->url);
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

    public function addContrast(): self
    {
        $this->image->contrastImage(1);
        return $this;
    }

    /**
     * @throws ImagickException
     * @throws ImagickPixelException
     */
    public function hasCloud(int $x, int $y, float $colorThreshold): bool
    {
        $color = $this->image->getImagePixelColor($x, $y)->getColor(true);
        return $color['a'] > $this->alphaThreshold &&
            array_sum(array_slice($color, 0, 3)) / 3 > $colorThreshold;
    }
}
