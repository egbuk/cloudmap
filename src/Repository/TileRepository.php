<?php

namespace App\Repository;

use App\Service\CloudUpdateService;
use Brick\Geo\Exception\GeometryException;
use Brick\Geo\IO\GeoJSON\FeatureCollection;
use Brick\Geo\IO\GeoJSONReader;
use Brick\Geo\IO\GeoJSONWriter;
use Exception;
use HeyMoon\VectorTileDataProvider\Contract\LayerInterface;
use HeyMoon\VectorTileDataProvider\Contract\SourceFactoryInterface;
use HeyMoon\VectorTileDataProvider\Contract\TileServiceInterface;
use HeyMoon\VectorTileDataProvider\Entity\Feature as FeatureEntity;
use HeyMoon\VectorTileDataProvider\Entity\TilePosition;
use HeyMoon\VectorTileDataProvider\Service\TileService;
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
    public function get(TilePosition $position)
    {
        return $this->cache->get($this->getKey($position),
            function (ItemInterface $item) use ($position) {
            if ($position->getZoom() <= CloudUpdateService::MAX_ZOOM) {
                return null;
            }
            $item->expiresAfter(60);
            return $this->getRaw($position);
        });
    }

    /**
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function getTile(TilePosition $position): ?Tile
    {
        $result = $this->cache->get($this->getKey($position), fn() => null);
        if (!$result) {
            return null;
        }
        $tile = new Tile();
        $tile->mergeFromString(gzdecode($result));
        return $tile;
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
    public function getRawLayer(TilePosition $position, string $name = 'clouds'): ?LayerInterface
    {
        $scale = pow(2, $position->getZoom() - CloudUpdateService::MAX_ZOOM);
        $data = $this->cache->get('raw'.$this->getKey(TilePosition::xyz(
                (int)floor($position->getColumn() / $scale),
                (int)floor($position->getRow() / $scale),
                CloudUpdateService::MAX_ZOOM
            )), fn() => null);
        if (!$data) {
            return null;
        }
        $source = $this->sourceFactory->create();
        $source->addCollection($name, $this->geoJSONReader->read(gzdecode($data)));
        return $source->getLayer($name);
    }

    /**
     * @throws GeometryException
     * @throws InvalidArgumentException
     */
    private function getRaw(TilePosition $position): ?string
    {
        return gzencode($this->tileService->getTileMVT(
            $this->getRawLayer($position)->getFeatures(), $position, TileService::DEFAULT_EXTENT, $position->getTileWidth() / 5
        )->serializeToString());
    }

    public function getCurrentTime(?int $advance = null): string
    {
        return date('H:00', $advance ? time()+60*$advance : null);
    }

    private function getKey(TilePosition $position): string
    {
        return md5($position);
    }
}
