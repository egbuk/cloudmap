<?php

namespace App\Service;

use App\Entity\Footage;
use Brick\Geo\Engine\GeometryEngine;
use Brick\Geo\Exception\CoordinateSystemException;
use Brick\Geo\Exception\GeometryEngineException;
use Brick\Geo\Exception\InvalidGeometryException;
use Brick\Geo\Geometry;
use Brick\Geo\IO\GeoJSON\Feature;
use Brick\Geo\IO\GeoJSON\FeatureCollection;
use Brick\Geo\LineString;
use Brick\Geo\Point;
use Brick\Geo\Polygon;
use HeyMoon\VectorTileDataProvider\Contract\SpatialServiceInterface;
use HeyMoon\VectorTileDataProvider\Spatial\WorldGeodeticProjection;
use ImagickException;
use ImagickPixelException;

readonly class CloudSearchService
{
    protected const TOLERANCE = 0.3;

    public function __construct(
        private SpatialServiceInterface $spatialService,
        private GeometryEngine $geometryEngine
    ) {}

    /**
     * @param Footage $footage
     * @return FeatureCollection
     * @throws CoordinateSystemException
     * @throws ImagickException
     * @throws ImagickPixelException
     * @throws InvalidGeometryException
     * @throws GeometryEngineException
     */
    public function process(Footage $footage): FeatureCollection
    {
        $clouds = [];
        foreach (range(1, $footage->getWidth()) as $x) {
            /** WebMercator from 5 to 185 degrees */
            foreach (range($footage->getHeight() / 180 * 5, $footage->getHeight() -
                ($footage->getHeight() / 180 * 5)) as $y) {
                if ($footage->hasCloud($x, $y)) {
                    $clouds[$x][$y] = true;
                }
            }
        }
        $edges = [];
        foreach (range(1, $footage->getWidth()) as $x) {
            foreach (range(1, $footage->getHeight()) as $y) {
                if (isset($clouds[$x][$y])) {
                    if ($x === 1 || $x === $footage->getWidth() ||
                        $y === 1 || $y === $footage->getHeight()) {
                        $edges[$x][$y] = true;
                    }
                    continue;
                }
                foreach (range(-1, 1) as $addX) {
                    foreach (range(-1, 1) as $addY) {
                        if (!$addX && !$addY) {
                            continue;
                        }
                        if (isset($clouds[$x + $addX][$y + $addY])) {
                            $edges[$x + $addX][$y + $addY] = true;
                        }
                    }
                }
            }
        }
        $clouds = [];
        foreach (array_keys($edges) as $x) {
            foreach (array_keys($edges[$x]) as $y) {
                if (empty($edges[$x][$y])) {
                    continue;
                }
                unset($edges[$x][$y]);
                $line = [$this->getPoint($x, $y, $footage)];
                $size = 0;
                while ($size < count($line)) {
                    $size = count($line);
                    foreach (range(-1, 1) as $addX) {
                        foreach (range(-1, 1) as $addY) {
                            if (!$addX && !$addY) {
                                continue;
                            }
                            if (isset($edges[$x + $addX][$y + $addY])) {
                                $x += $addX;
                                $y += $addY;
                                $line[] = $this->getPoint($x, $y, $footage);
                                unset($edges[$x][$y]);
                                break 2;
                            }
                        }
                    }
                }
                if (count($line) < 2) {
                    continue;
                }
                $line[] = reset($line);
                $cloud = $this->geometryEngine->buffer(Polygon::of(LineString::of(
                    ...array_map(fn(Point $point) => $this->spatialService->transformPoint(
                    $point, WorldGeodeticProjection::SRID), $line))), self::TOLERANCE);
                if ($cloud->isEmpty()) {
                    continue;
                }
                $clouds[] = $cloud;
            }
        }
        return new FeatureCollection(...array_map(fn(Geometry $cloud) => new Feature($cloud), $clouds));
    }

    protected function getPoint(int $x, int $y, Footage $footage): Point
    {
        return Point::xy((float)$x / ((float)$footage->getWidth()) * 360 - 180,
            (float)$y / ((float)$footage->getHeight()) * 180 - 90,
            WorldGeodeticProjection::SRID);
    }
}
