<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;

readonly class StyleService
{
    public function __construct(private string $stylePath,
                                private string $mapTilerToken,
                                private Filesystem $filesystem) {}

    public function getStyle(string $host, string $time): array
    {
        $filter = ['==', 'time', $time];
        return array_merge_recursive(json_decode($this->filesystem->readFile($this->stylePath), true), [
            'glyphs' => "https://api.maptiler.com/fonts/{fontstack}/{range}.pbf?key=$this->mapTilerToken",
            'sources' => [
                'contours' => [
                    'url' => "https://api.maptiler.com/tiles/contours-v2/tiles.json?key=$this->mapTilerToken",
                    'type' => 'vector'
                ],
                'maptiler_planet' => [
                    'url' => "https://api.maptiler.com/tiles/v3/tiles.json?key=$this->mapTilerToken",
                    'type' => 'vector'
                ],
                'outdoor' => [
                    'url' => "https://api.maptiler.com/tiles/outdoor/tiles.json?key=$this->mapTilerToken",
                    'type' => 'vector'
                ],
                'terrain-rgb' => [
                    'url' => "https://api.maptiler.com/tiles/terrain-rgb-v2/tiles.json?key=$this->mapTilerToken",
                    'type' => 'raster-dem'
                ],
                'clouds' => [
                    'type' => 'vector',
                    'tiles' => [
                        "$host/clouds?x={x}&y={y}&z={z}"
                    ],
                    'minZoom' => 0,
                    'maxZoom' => 22,
                    'attribution' => 'Contains modified <a href="https://www.eumetsat.int" target="_blank">EUMETSAT</a> data '.date('Y')
                ]
            ],
            'layers' => array_merge(...array_map(fn(string $transition) => [
                [
                    'id' => "cloud_shadow_$transition",
                    'type' => 'fill',
                    'source' => 'clouds',
                    'source-layer' => 'clouds',
                    'paint' => [
                        'fill-color' => '#000',
                        'fill-translate' => [1, 1],
                        'fill-opacity' => $transition === 'a' ? 0.3 : 0,
                        'fill-opacity-transition' => ['duration' => 500]
                    ],
                    'filter' => $filter
                ],
                [
                    'id' => "cloud_sky_$transition",
                    'type' => 'fill-extrusion',
                    'source' => 'clouds',
                    'source-layer' => 'clouds',
                    'paint' => [
                        'fill-extrusion-base' => 6000,
                        'fill-extrusion-height' => 7000,
                        'fill-extrusion-color' => '#fff',
                        'fill-extrusion-opacity' => $transition === 'a' ? 0.5 : 0,
                        'fill-extrusion-opacity-transition' => ['duration' => 500]
                    ],
                    'filter' => $filter
                ]
                ], ['a', 'b']))
        ]);
    }
}
