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
    public const MAX_ZOOM = 6;

    public function __construct(
        private FootageService $footageService,
        private CloudSearchService $cloudSearchService,
        private SourceFactoryInterface $sourceFactory,
        private GridServiceInterface $gridService,
        private TileServiceInterface $tileService,
        private TileRepository $tileRepository,
        private int $colorThreshold,
        private int $fadeColorThreshold
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
        $time = $this->tileRepository->getCurrentTime(15);
        $source = $this->sourceFactory->create();
        $footage = $this->footageService->get();
        $source->addCollection('clouds_a', $this->cloudSearchService->process($footage,
            compact('time'), $this->fadeColorThreshold));
        $source->addCollection('clouds_b', $this->cloudSearchService->process($footage->addContrast(),
            compact('time'), $this->colorThreshold));
        $this->footageService->clear($footage);
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
                    $preserved), $position, TileServiceInterface::DEFAULT_EXTENT,
                    $position->getTileWidth() / 10));
            });
            if ($zoom === static::MAX_ZOOM) {
                $grid->iterate(fn (TilePosition $position, array $clouds) =>
                $this->tileRepository->storeRaw($position, array_merge($clouds, array_filter(
                    $this->tileRepository->getRawData($position)?->getFeatures() ?? [],
                    fn(Feature $feature) => $feature->getParameter('time') !== $time
                ))));
            }
            $progressBar?->advance();
        }
    }
}
