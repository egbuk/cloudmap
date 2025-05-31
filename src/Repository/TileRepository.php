<?php

namespace App\Repository;

use HeyMoon\VectorTileDataProvider\Entity\TilePosition;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Vector_tile\Tile;

readonly class TileRepository
{
    public function __construct(
        private CacheInterface $cache
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
        return $this->cache->get($position, fn() => null);
    }
}
