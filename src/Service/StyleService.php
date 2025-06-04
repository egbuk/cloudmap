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
            'layers' => [
                [
                    'id' => 'cloud',
                    'type' => 'fill',
                    'source' => 'clouds',
                    'source-layer' => 'clouds',
                    'paint' => [
                        'fill-color' => 'rgba(255, 255, 255, 0.5)'
                    ],
                    'filter' => $filter
                ],
                [
                    'id' => 'cloud_edge',
                    'type' => 'line',
                    'source' => 'clouds',
                    'source-layer' => 'clouds',
                    'paint' => [
                        'line-color' => 'rgba(85, 191, 255, 0.7)'
                    ],
                    'filter' => $filter
                ]
            ]
        ]);
    }
}
