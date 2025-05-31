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
        $this->cache->delete($position);
        return $this->cache->get($position, fn() => gzencode($tile->serializeToString()));
    }

    /**
     * @throws InvalidArgumentException
     */
    public function get(TilePosition $position)
    {
        return $this->cache->get($position, function (ItemInterface $item) use ($position) {
            $item->expiresAfter(300);
            return $position->getZoom() > CloudUpdateService::MAX_ZOOM ?
                $this->getRaw($position) : null;
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
        $key = "raw$position";
        $this->cache->delete($key);
        return $this->cache->get($key, fn() => gzencode($this->geoJSONWriter->write(new FeatureCollection(
            ...array_map(fn(FeatureEntity $feature) => $feature->asGeoJSONFeature(), $clouds)))));
    }

    /**
     * @throws GeometryException
     * @throws InvalidArgumentException
     */
    private function getRaw(TilePosition $position): ?string
    {
        $scale = pow(2, $position->getZoom() - CloudUpdateService::MAX_ZOOM);
        $data = $this->cache->get('raw'.TilePosition::xyz(
                (int)floor($position->getColumn() / $scale),
                (int)floor($position->getRow() / $scale),
                CloudUpdateService::MAX_ZOOM
            ), fn() => null);
        if (!$data) {
            return null;
        }
        $source = $this->sourceFactory->create();
        $source->addCollection('clouds', $this->geoJSONReader->read(gzdecode($data)));
        return gzencode($this->tileService->getTileMVT($source->getFeatures(), $position)->serializeToString());
    }
}
