<?php

namespace App\Repository;

use App\Service\CloudUpdateService;
use Brick\Geo\Exception\GeometryException;
use Brick\Geo\IO\GeoJSON\FeatureCollection;
use Brick\Geo\IO\GeoJSONReader;
use Brick\Geo\IO\GeoJSONWriter;
use HeyMoon\VectorTileDataProvider\Contract\SourceFactoryInterface;
use HeyMoon\VectorTileDataProvider\Contract\TileServiceInterface;
use HeyMoon\VectorTileDataProvider\Entity\Feature as FeatureEntity;
use HeyMoon\VectorTileDataProvider\Entity\TilePosition;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Vector_tile\Tile;

readonly class TileRepository
{
    public function __construct(
        private CacheInterface $cache,
        private GeoJSONWriter $geoJSONWriter,
        private GeoJSONReader $geoJSONReader,
        private SourceFactoryInterface $sourceFactory,
        private TileServiceInterface $tileService
    ) {}

    /**
     * @throws InvalidArgumentException
     */
    public function store(TilePosition $position, Tile $tile)
    {
        $key = $this->getKey($position);
        $this->cache->delete($key);
        return $this->cache->get($key,
            function (ItemInterface $item) use ($tile) {
            $item->expiresAfter(86400);
            return gzencode($tile->serializeToString());
        });
    }

    /**
     * @throws InvalidArgumentException
     */
    public function get(TilePosition $position, string $time)
    {
        return $this->cache->get($this->getKey($position, $time),
            function (ItemInterface $item) use ($position, $time) {
            if ($position->getZoom() <= CloudUpdateService::MAX_ZOOM) {
                return null;
            }
            $item->expiresAfter(60);
            return $this->getRaw($position, $time);
        });
    }

    /**
     * @param TilePosition $position
     * @param FeatureEntity[] $clouds
     * @return TilePosition|mixed
     * @throws InvalidArgumentException
     */
    public function storeRaw(TilePosition $position, array $clouds): mixed
    {
        $key = "raw{$this->getKey($position)}";
        $this->cache->delete($key);
        return $this->cache->get($key, function(ItemInterface $item) use ($clouds) {
            $item->expiresAfter(86400);
            return gzencode($this->geoJSONWriter->write(new FeatureCollection(
                ...array_map(fn(FeatureEntity $feature) => $feature->asGeoJSONFeature(), $clouds))));
        });
    }

    /**
     * @throws GeometryException
     * @throws InvalidArgumentException
     */
    private function getRaw(TilePosition $position, string $time): ?string
    {
        $scale = pow(2, $position->getZoom() - CloudUpdateService::MAX_ZOOM);
        $data = $this->cache->get('raw'.$this->getKey(TilePosition::xyz(
                (int)floor($position->getColumn() / $scale),
                (int)floor($position->getRow() / $scale),
                CloudUpdateService::MAX_ZOOM
            ), $time), fn() => null);
        if (!$data) {
            return null;
        }
        $source = $this->sourceFactory->create();
        $source->addCollection('clouds', $this->geoJSONReader->read(gzdecode($data)));
        return gzencode($this->tileService->getTileMVT($source->getFeatures(), $position)->serializeToString());
    }

    public function getCurrentTime(): string
    {
        return date('H:00');
    }

    private function getKey(TilePosition $position, ?string $time = null): string
    {
        return md5(($time ?? $this->getCurrentTime()).$position);
    }
}
