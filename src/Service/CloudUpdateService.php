<?php

namespace App\Service;

use App\Repository\TileRepository;
use Brick\Geo\Exception\CoordinateSystemException;
use Brick\Geo\Exception\GeometryEngineException;
use Brick\Geo\Exception\InvalidGeometryException;
use HeyMoon\VectorTileDataProvider\Contract\GridServiceInterface;
use HeyMoon\VectorTileDataProvider\Contract\SourceFactoryInterface;
use HeyMoon\VectorTileDataProvider\Contract\TileServiceInterface;
use HeyMoon\VectorTileDataProvider\Entity\TilePosition;
use ImagickException;
use ImagickPixelException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Console\Helper\ProgressBar;

readonly class CloudUpdateService
{
    protected const MAX_ZOOM = 14;

    public function __construct(
        private FootageService $footageService,
        private CloudSearchService $cloudSearchService,
        private SourceFactoryInterface $sourceFactory,
        private GridServiceInterface $gridService,
        private TileServiceInterface $tileService,
        private TileRepository $tileRepository
    ) {}

    /**
     * @throws CoordinateSystemException
     * @throws GeometryEngineException
     * @throws ImagickException
     * @throws ImagickPixelException
     * @throws InvalidGeometryException
     * @throws InvalidArgumentException
     */
    public function update(?ProgressBar $progressBar = null): void
    {
        $footage = $this->footageService->get();
        $clouds = $this->cloudSearchService->process($footage);
        $this->footageService->clear($footage);
        $source = $this->sourceFactory->create();
        $source->addCollection('clouds', $clouds);
        $progressBar?->setMaxSteps(static::MAX_ZOOM);
        foreach (range(0, 14) as $zoom) {
            $grid = $this->gridService->getGrid($source, $zoom);
            $grid->iterate(fn(TilePosition $position, array $clouds) => $this->tileRepository->store(
                $position, $this->tileService->getTileMVT($clouds, $position)));
            $progressBar?->advance();
        }
    }
}
