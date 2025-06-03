<?php

namespace App\Service;

use App\Repository\TileRepository;
use Brick\Geo\Exception\CoordinateSystemException;
use Brick\Geo\Exception\GeometryEngineException;
use Brick\Geo\Exception\GeometryException;
use Brick\Geo\Exception\InvalidGeometryException;
use HeyMoon\VectorTileDataProvider\Contract\GridServiceInterface;
use HeyMoon\VectorTileDataProvider\Contract\SourceFactoryInterface;
use HeyMoon\VectorTileDataProvider\Contract\TileServiceInterface;
use HeyMoon\VectorTileDataProvider\Entity\Feature;
use HeyMoon\VectorTileDataProvider\Entity\TilePosition;
use ImagickException;
use ImagickPixelException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Console\Helper\ProgressBar;

readonly class CloudUpdateService
{
    public const MAX_ZOOM = 5;

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
     * @throws GeometryException
     */
    public function update(?ProgressBar $progressBar = null): void
    {
        $footage = $this->footageService->get();
        $time = $this->tileRepository->getCurrentTime(5);
        $clouds = $this->cloudSearchService->process($footage, compact('time'));
        $this->footageService->clear($footage);
        $source = $this->sourceFactory->create();
        $source->addCollection('clouds', $clouds);
        $progressBar?->setMaxSteps(static::MAX_ZOOM + 1);
        foreach (range(0, static::MAX_ZOOM) as $zoom) {
            $grid = $this->gridService->getGrid($source, $zoom);
            $grid->iterate(function (TilePosition $position, array $clouds) use ($time) {
                $preserved = [];
                $tile = $this->tileRepository->getTile($position);
                foreach ($tile?->getLayers() ?? [] as $layer) {
                    $decoded = $this->tileService->decodeGeometry($layer, $position);
                    foreach ($decoded->getFeatures() as $feature) {
                        if ($feature->getParameter('time') === $time) {
                            continue;
                        }
                        $preserved[] = $feature;
                    }
                }
                $this->tileRepository->store($position, $this->tileService->getTileMVT(array_merge($clouds,
                    $preserved), $position));
            });
            if ($zoom === static::MAX_ZOOM) {
                $grid->iterate(function (TilePosition $position, array $clouds) use ($time) {
                    $preserved = [];
                    $decoded = $this->tileRepository->getRawLayer($position);
                    /** @var Feature $feature */
                    foreach ($decoded?->getFeatures() ?? [] as $feature) {
                        if ($feature->getParameter('time') === $time) {
                            continue;
                        }
                        $preserved[] = $feature;
                    }
                    $this->tileRepository->storeRaw($position, array_merge($clouds, $preserved));
                });
            }
            $progressBar?->advance();
        }
    }
}
